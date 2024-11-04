<?php

namespace fostercommerce\shipstationconnect\controllers;

use Craft;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;
use craft\db\Query;
use craft\db\Table;
use craft\elements\MatrixBlock;
use craft\fields\Matrix;
use craft\helpers\App;
use craft\helpers\ElementHelper;
use craft\models\MatrixBlockType;
use craft\web\Application;
use craft\web\Controller;
use fostercommerce\shipstationconnect\events\FindOrderEvent;
use fostercommerce\shipstationconnect\models\Settings;
use fostercommerce\shipstationconnect\Plugin;
use yii\base\ErrorException;
use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\web\HttpException;
use yii\web\Response as YiiResponse;

/**
 * @phpstan-type ShippingInfo array{carrier: string, service: string, trackingNumber: string}
 */
class OrdersController extends Controller
{
	public const FIND_ORDER_EVENT = 'findOrderEvent';

	// Disable CSRF validation for the entire controller
	public $enableCsrfValidation = false;

	protected array|int|bool $allowAnonymous = true;

	public function init(): void
	{
		// Allow anonymous access only when this plugin is handling basic
		// authentication, otherwise require auth so that Craft doesn't let
		// unauthenticated requests go through.
		$isUsingCraftAuth = Plugin::getInstance()?->isAuthHandledByCraft();
		$this->allowAnonymous = ! $isUsingCraftAuth;
		if ($isUsingCraftAuth) {
			$this->requirePermission('shipstationconnect-processOrders');
		}

		parent::init();
	}

	/**
	 * ShipStation will hit this action for processing orders, both POSTing and GETting.
	 *   ShipStation will send a GET param 'action' of either shipnotify or export.
	 *   If this is not found or is any other string, this will throw a 400 exception.
	 *
	 * @throws HttpException
	 */
	public function actionProcess(?string $store = null, ?string $action = null): YiiResponse
	{
		if (! $this->authenticate()) {
			throw new HttpException(401, 'Invalid ShipStation username or password.');
		}

		switch ($action) {
			case 'export':
				$this->getOrders($store);
				// no break
			case 'shipnotify':
				return $this->postShipment();
			default:
				throw new HttpException(400, 'No action set. Set the ?action= parameter as `export` or `shipnotify`.');
		}
	}

	/**
	 * Authenticate the user using HTTP Basic auth. This is *not* using Craft's sessions/authentication.
	 *
	 * @return bool, true if successfully authenticated or false otherwise
	 */
	protected function authenticate(): bool
	{
		$plugin = Plugin::getInstance();
		if ($plugin?->isAuthHandledByCraft()) {
			return true;
		}

		$expectedUsername = App::parseEnv($plugin?->settings->shipstationUsername);
		$expectedPassword = App::parseEnv($plugin?->settings->shipstationPassword);

		[$username, $password] = $this->getApp()->getRequest()->getAuthCredentials();

		return $expectedUsername === $username && $expectedPassword === $password;
	}

	/**
	 * Returns a big XML object of all orders in a format described by ShipStation
	 *
	 * @throws InvalidConfigException
	 */
	protected function getOrders(?string $store = null): void
	{
		$query = Order::find();

		$start_date = $this->parseDate('start_date');
		$end_date = $this->parseDate('end_date');

		if ($start_date && $end_date) {
			$query->dateUpdated(['and', '> ' . $start_date, '< ' . $end_date]);
		}

		$query->isCompleted(true);

		$storeFieldHandle = Plugin::getInstance()?->settings->storesFieldHandle;
		if ($store !== null && $storeFieldHandle !== '' && $storeFieldHandle !== null) {
			$field = Craft::$app->getFields()->getFieldByHandle($storeFieldHandle);
			if ($field === null) {
				throw new \RuntimeException("Invalid field handle {$storeFieldHandle}");
			}

			$fieldColumnName = ElementHelper::fieldColumnFromField($field);
			$query->andWhere([
				$fieldColumnName => $store,
			]);
		}

		$query->orderBy('dateUpdated asc');

		$pageCount = $this->paginateOrders($query);

		$parentXml = new \SimpleXMLElement('<Orders />');
		$parentXml->addAttribute('pages', (string) $pageCount);

		/** @var string $xmlString */
		$xmlString = Plugin::getInstance()?->xml->generateXml($query->all(), $pageCount);

		$this->returnXml($xmlString);
	}

	/**
	 * For a Criteria instance of Orders, return the number of total pages and apply a corresponding offset and limit
	 *
	 * @return int total number of pages
	 */
	protected function paginateOrders(OrderQuery $query): int
	{
		$pageSize = Plugin::getInstance()->settings->ordersPageSize ?? Settings::DEFAULT_PAGE_SIZE;
		if (! is_numeric($pageSize) || $pageSize < 1) {
			$pageSize = Settings::DEFAULT_PAGE_SIZE;
		}

		$count = (int) $query->count();
		$numPages = (int) ceil($count / $pageSize);
		$pageNum = $this->getApp()->getRequest()->getParam('page');
		$pageNum = ! is_numeric($pageNum) || $pageNum < 1 ? 1 : (int) $pageNum;

		$query->limit($pageSize);
		$query->offset(($pageNum - 1) * $pageSize);

		return $numPages;
	}

	/**
	 * For a given date field, parse and return its date as a string
	 *
	 * @param string $fieldName, the name of the field in GET params
	 * @return ?string the formatted date string
	 */
	protected function parseDate(string $fieldName): ?string
	{
		/** @var string $dateRaw */
		$dateRaw = $this->getApp()->getRequest()->getParam($fieldName);
		if ($dateRaw) {
			$date = strtotime($dateRaw);
			if ($date !== false) {
				if ($fieldName === 'start_date') {
					return date('Y-m-d H:i:s', $date);
				}

				return date('Y-m-d H:i:59', $date);
			}
		}

		return null;
	}

	/**
	 * Updates order status for a given order. This is called by ShipStation.
	 * The order is found using the query param `order_number`.
	 *
	 * TODO: This assumes there is a "shipped" handle for an order status
	 *
	 * See craft/plugins/commerce/controllers/Commerce_OrdersController.php#actionUpdateStatus() for details
	 *
	 * @throws ErrorException if the order fails to save
	 * @throws HttpException
	 */
	protected function postShipment(): YiiResponse
	{
		$order = $this->orderFromParams();

		$settings = Plugin::getInstance()?->settings;

		$shippedStatusHandle = $settings?->shippedStatusHandle ?? '';
		$matrixFieldHandle = $settings?->matrixFieldHandle ?? '';
		$blockTypeHandle = $settings?->blockTypeHandle ?? '';
		$carrierFieldHandle = $settings?->carrierFieldHandle ?? '';
		$serviceFieldHandle = $settings?->serviceFieldHandle ?? '';
		$trackingNumberFieldHandle = $settings?->trackingNumberFieldHandle ?? '';

		if ($shippedStatusHandle === '' || $matrixFieldHandle === '' || $blockTypeHandle === '' || $carrierFieldHandle === '' || $serviceFieldHandle === '' || $trackingNumberFieldHandle === '') {
			throw new \RuntimeException('Invalid or missing handle config');
		}

		$status = CommercePlugin::getInstance()
			?->orderStatuses
			->getOrderStatusByHandle($shippedStatusHandle);
		if (! $status) {
			throw new ErrorException('Failed to find shipped order status');
		}

		$order->orderStatusId = $status->id;
		$order->message = 'Marking order as shipped. Adding shipping information.';
		$shippingInformation = $this->getShippingInformationFromParams();

		/** @var ?Matrix $matrix */
		$matrix = Craft::$app->fields->getFieldByHandle($matrixFieldHandle);

		// If the field exists
		if ($matrix !== null) {
			$blockType = $this->getBlockTypeByHandle($matrix->id, $blockTypeHandle);

			if ($blockType instanceof MatrixBlockType && $this->validateShippingInformation($shippingInformation)) {
				$block = new MatrixBlock([
					'ownerId' => $order->id,
					'fieldId' => $matrix->id,
					'typeId' => $blockType->id,
				]);
				$block->setFieldValue($carrierFieldHandle, $shippingInformation['carrier']);
				$block->setFieldValue($serviceFieldHandle, $shippingInformation['service']);
				$block->setFieldValue($trackingNumberFieldHandle, $shippingInformation['trackingNumber']);

				if (! Craft::$app->elements->saveElement($block)) {
					Craft::warning(
						Craft::t(
							'shipstationconnect',
							'Unable to save shipping information.'
						),
						__METHOD__
					);
				}
			}
		} else {
			Craft::warning(
				Craft::t(
					'shipstationconnect',
					'Missing shippingInfo Matrix field. Ignoring.'
				),
				__METHOD__
			);
		}

		if (Craft::$app->elements->saveElement($order, false)) {
			return $this->asJson([
				'success' => true,
			]);
		}

		throw new ErrorException('Failed to save order with id ' . $order->id);
	}

	/**
	 * Parse parameters POSTed from ShipStation for fields available to us on the Order's shippingInfo matrix field
	 *
	 * Note: only fields that exist in the matrix block will be set.
	 *       ShipStation posts, in XML, many more fields than these, but for now we disregard.
	 *       https://help.shipstation.com/hc/en-us/articles/205928478-ShipStation-Custom-Store-Development-Guide#2ai
	 *
	 * @return ShippingInfo
	 */
	protected function getShippingInformationFromParams(): array
	{
		$request = $this->getApp()->getRequest();

		/** @var string $carrier */
		$carrier = $request->getParam('carrier');

		/** @var string $service */
		$service = $request->getParam('service');

		/** @var string $trackingNumber */
		$trackingNumber = $request->getParam('tracking_number');
		return [
			'carrier' => $carrier,
			'service' => $service,
			'trackingNumber' => $trackingNumber,
		];
	}

	/**
	 * Find the order model given the order_number passed to us from ShipStation.
	 *
	 * Note: the order_number value from ShipStation corresponds to $order->number that we
	 *       return to ShipStation as part of the getOrders() method above.
	 *
	 * @throws HttpException, 404 if not found, 406 if order number is invalid
	 */
	protected function orderFromParams(): Order
	{
		/** @var ?string $orderNumber */
		$orderNumber = $this->getApp()->getRequest()->getParam('order_number');
		if ($orderNumber !== null && $orderNumber !== '') {
			$findOrderEvent = new FindOrderEvent([
				'orderNumber' => $orderNumber,
			]);
			Event::trigger(static::class, self::FIND_ORDER_EVENT, $findOrderEvent);

			$order = $findOrderEvent->order;
			if (! $order instanceof Order) {
				if ($order = Order::find()->reference($orderNumber)->one()) {
					/** @var Order $order */
					return $order;
				}

				throw new HttpException(404, "Order with number '{$orderNumber}' not found");
			}

			return $order;
		}

		throw new HttpException(406, 'Order number must be set');
	}

	/**
	 * Responds to the request with XML.
	 *
	 * See craft/app/controllers/BaseController.php#returnJson() for comparisons
	 */
	protected function returnXml(string $xml): void
	{
		header('Content-type: text/xml');
		// Output it into a buffer, in case TasksService wants to close the connection prematurely
		ob_start();
		echo $xml;
		exit(0);
	}

	private function getApp(): Application
	{
		/** @var Application $app */
		$app = Craft::$app;
		return $app;
	}

	private function getBlockTypeByHandle(int|string|null $fieldId, string $handle): ?MatrixBlockType
	{
		/** @var ?array<string, mixed> $result */
		$result = (new Query())
			->select([
				'id',
				'fieldId',
				'fieldLayoutId',
				'name',
				'handle',
				'sortOrder',
				'uid',
			])
			->from([Table::MATRIXBLOCKTYPES])
			->where([
				'fieldId' => $fieldId,
			])
			->andWhere([
				'handle' => $handle,
			])
			->orderBy([
				'sortOrder' => SORT_ASC,
			])
			->one();

		if ($result !== null) {
			return new MatrixBlockType($result);
		}

		return null;
	}

	/**
	 * @param ShippingInfo $info
	 */
	private function validateShippingInformation(array $info): bool
	{
		// Requires at least one value
		foreach ($info as $value) {
			if ($value && trim($value) !== '') {
				return true;
			}
		}

		return false;
	}
}
