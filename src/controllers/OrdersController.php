<?php
namespace fostercommerce\shipstationconnect\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\MatrixBlock;
use craft\helpers\ElementHelper;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\elements\Order;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\App;
use craft\web\Response;
use craft\models\MatrixBlockType;
use yii\web\HttpException;
use yii\base\ErrorException;
use yii\base\Event;
use yii\web\Response as YiiResponse;
use fostercommerce\shipstationconnect\Plugin;
use fostercommerce\shipstationconnect\events\FindOrderEvent;

class OrdersController extends Controller
{
    const FIND_ORDER_EVENT = 'findOrderEvent';

    // Disable CSRF validation for the entire controller
    public $enableCsrfValidation = false;

    protected array|int|bool $allowAnonymous = true;

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        // Allow anonymous access only when this plugin is handling basic
        // authentication, otherwise require auth so that Craft doesn't let
        // unauthenticated requests go through.
        $isUsingCraftAuth = Plugin::getInstance()->isAuthHandledByCraft();
        $this->allowAnonymous = !$isUsingCraftAuth;
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
     * @param array $variables, containing key 'fulfillmentService'
     * @throws HttpException for malformed requests
     */
    public function actionProcess($store = null, $action = null): null|string|Response|YiiResponse|SimpleXMLElement
    {
        $request = Craft::$app->request;
        try {
            if (!$this->authenticate()) {
                throw new HttpException(401, 'Invalid ShipStation username or password.');
            }

            switch ($action) {
                case 'export':
                    return $this->getOrders($store);
                case 'shipnotify':
                    return $this->postShipment();
                default:
                    throw new HttpException(400, 'No action set. Set the ?action= parameter as `export` or `shipnotify`.');
            }
        } catch (ErrorException $e) {
            $this->logException('Error processing action {action}', ['action' => $action], $e);
            return $this->asErrorJson($e->getMessage())->setStatusCode(500);
        } catch (HttpException $e) {
           
            if ($action) {
                $this->logException('Error processing action {action}', ['action' => $action], $e);
            } else {
                $this->logException('An action is required. Supported actions: export, shipnotify.');
            }

            return $this->asErrorJson($e->getMessage())->setStatusCode($e->statusCode);
        } catch (\Exception $e) {
            $this->logException('Error processing action {action}', ['action' => $action], $e);
            return $this->asErrorJson($e->getMessage())->setStatusCode(500);
        }
    }

    private function logException($msg, $params = [], $e = null): void
    {
        Craft::error(
            Craft::t('shipstationconnect', $msg, $params),
            __METHOD__
        );

        if ($e) {
            Craft::$app->getErrorHandler()->logException($e);
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
        if ($plugin->isAuthHandledByCraft()) {
            return true;
        }

        $settings = $plugin->settings;
        $expectedUsername = App::parseEnv($settings->shipstationUsername);
        $expectedPassword = App::parseEnv($settings->shipstationPassword);

        list($username, $password) = Craft::$app->getRequest()->getAuthCredentials();

        return $expectedUsername === $username && $expectedPassword === $password;
    }

    /**
     * Returns a big XML object of all orders in a format described by ShipStation
     *
     * @return SimpleXMLElement Orders XML
     */
    protected function getOrders($store = null): SimpleXMLElement
    {
        $query = Order::find();

        $start_date = $this->parseDate('start_date');
        $end_date = $this->parseDate('end_date');

        if ($start_date && $end_date) {
            $query->dateUpdated(array('and', '> '.$start_date, '< '.$end_date));
        }

        $query->isCompleted(true);

        $storeFieldHandle = Plugin::getInstance()->settings->storesFieldHandle;
        if ($store !== null || $storeFieldHandle !== '') {
            $field = Craft::$app->getFields()->getFieldByHandle($storeFieldHandle);
            $fieldColumnName = ElementHelper::fieldColumnFromField($field);
            $query->andWhere([$fieldColumnName => $store]);
        }

        $query->orderBy('dateUpdated asc');

        $num_pages = $this->paginateOrders($query);

        $parent_xml = new \SimpleXMLElement('<Orders />');
        $parent_xml->addAttribute('pages', $num_pages);

        Plugin::getInstance()->xml->orders($parent_xml, $query->all());

        $this->returnXML($parent_xml);
    }

    /**
     * For a Criteria instance of Orders, return the number of total pages and apply a corresponding offset and limit
     *
     * @param ElementCriteriaModel, a REFERENCE to the criteria instance
     * @return Int total number of pages
     */
    protected function paginateOrders(&$query): int
    {
        $pageSize = Plugin::getInstance()->settings->ordersPageSize;
        if (!is_numeric($pageSize) || $pageSize < 1) {
            $pageSize = 25;
        }

        $numPages = ceil($query->count() / $pageSize);
        $pageNum = Craft::$app->getRequest()->getParam('page');
        if (!is_numeric($pageNum) || $pageNum < 1) {
            $pageNum = 1;
        }

        $query->limit($pageSize);
        $query->offset(($pageNum - 1) * $pageSize);

        return $numPages;
    }

    /**
     * For a given date field, parse and return its date as a string
     *
     * @param String $field_name, the name of the field in GET params
     * @return String|null the formatted date string
     */
    protected function parseDate($field_name): ?string
    {
        $request = Craft::$app->getRequest();
        if ($date_raw = $request->getParam($field_name)) {
            $date = strtotime($date_raw);
            if ($date !== false) {
                if ($field_name === 'start_date') {
                    return date('Y-m-d H:i:s', $date);
                } else {
                    return date('Y-m-d H:i:59', $date);
                }
            }
        }
        return null;
    }

    private function getBlockTypeByHandle($fieldId, $handle): ?MatrixBlockType
    {
        $result = (new Query())
            ->select([
                'id',
                'fieldId',
                'fieldLayoutId',
                'name',
                'handle',
                'sortOrder',
                'uid'
            ])
            ->from([Table::MATRIXBLOCKTYPES])
            ->where(['fieldId' => $fieldId])
            ->andWhere(['handle' => $handle])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->one();

        if ($result) {
            return new MatrixBlockType($result);
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
     */
    protected function postShipment(): null|string|Response
    {
        $order = $this->orderFromParams();

        $settings = Plugin::getInstance()->settings;

        $status = CommercePlugin::getInstance()
            ->orderStatuses
            ->getOrderStatusByHandle($settings->shippedStatusHandle);
        if (!$status) {
            throw new ErrorException("Failed to find shipped order status");
        }

        $order->orderStatusId = $status->id;
        $order->message = 'Marking order as shipped. Adding shipping information.';
        $shippingInformation = $this->getShippingInformationFromParams();

        $matrix = Craft::$app->fields->getFieldByHandle($settings->matrixFieldHandle);

        // If the field exists
        if ($matrix) {
            $blockType = $this->getBlockTypeByHandle($matrix->id, $settings->blockTypeHandle);

            if ($blockType && $order && $this->validateShippingInformation($shippingInformation)) {
                $block = new MatrixBlock([
                    'ownerId' => $order->id,
                    'fieldId' => $matrix->id,
                    'typeId' => $blockType->id,
                ]);
                $block->setFieldValue($settings->carrierFieldHandle, $shippingInformation['carrier']);
                $block->setFieldValue($settings->serviceFieldHandle, $shippingInformation['service']);
                $block->setFieldValue($settings->trackingNumberFieldHandle, $shippingInformation['trackingNumber']);

                if (!Craft::$app->elements->saveElement($block)) {
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
            return $this->asJson(['success' => true]);
        } else {
            throw new ErrorException('Failed to save order with id ' . $order->id);
        }
    }

    private function validateShippingInformation($info): bool
    {
        // Requires at least one value
        foreach ($info as $key => $value) {
            if ($value && !empty(trim($value))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse parameters POSTed from ShipStation for fields available to us on the Order's shippingInfo matrix field
     *
     * Note: only fields that exist in the matrix block will be set.
     *       ShipStation posts, in XML, many more fields than these, but for now we disregard.
     *       https://help.shipstation.com/hc/en-us/articles/205928478-ShipStation-Custom-Store-Development-Guide#2ai
     *
     * @return array
     */
    protected function getShippingInformationFromParams(): array
    {
        $request = Craft::$app->getRequest();
        return [
            'carrier' => $request->getParam('carrier'),
            'service' => $request->getParam('service'),
            'trackingNumber' => $request->getParam('tracking_number'),
        ];
    }

    /**
     * Find the order model given the order_number passed to us from ShipStation.
     *
     * Note: the order_number value from ShipStation corresponds to $order->number that we
     *       return to ShipStation as part of the getOrders() method above.
     *
     * @throws HttpException, 404 if not found, 406 if order number is invalid
     * @return Order
     */
    protected function orderFromParams(): Order
    {
        $request = Craft::$app->getRequest();
        if ($orderNumber = $request->getParam('order_number')) {
            $findOrderEvent = new FindOrderEvent(['orderNumber' => $orderNumber]);
            Event::trigger(static::class, self::FIND_ORDER_EVENT, $findOrderEvent);

            $order = $findOrderEvent->order;
            if (!$order) {
                if ($order = Order::find()->reference($orderNumber)->one()) {
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
     *
     * @param SimpleXMLElement $xml
     * @return null
     */
    protected function returnXML(\SimpleXMLElement $xml): void
    {
        header("Content-type: text/xml");
        // Output it into a buffer, in case TasksService wants to close the connection prematurely
        ob_start();
        echo $xml->asXML();

        exit(0);
    }
}
