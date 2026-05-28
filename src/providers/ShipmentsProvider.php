<?php

declare(strict_types=1);

namespace fostercommerce\shipstationconnect\providers;

use BackedEnum;
use Craft;
use craft\commerce\elements\Order;
use craft\helpers\App;
use craft\helpers\Json;
use craft\web\Request;
use craft\web\Response;
use craft\web\View;
use fostercommerce\shipments\base\Provider;
use fostercommerce\shipments\elements\Shipment;
use fostercommerce\shipments\enums\FulfillmentStatus;
use fostercommerce\shipments\enums\ShippingStatus;
use fostercommerce\shipments\errors\PermanentIntegrationException;
use fostercommerce\shipments\models\ShipmentExportQuery;
use fostercommerce\shipments\models\ShipmentUpdatePayload;
use fostercommerce\shipments\Plugin as ShipmentsPlugin;
use fostercommerce\shipstationconnect\Plugin as ShipStationConnectPlugin;
use yii\web\UnauthorizedHttpException;

/**
 * Shipments-plugin provider that surfaces each Shipment to ShipStation as a custom-store
 * order. Pull-based: ShipStation hits `shipstationconnect/shipments/<integrationHandle>`
 * with `?action=export` to retrieve XML, and POSTs the same URL with `?action=shipnotify`
 * to push tracking + mark fulfilled.
 *
 * `sendShipment` only records a local integration reference. ShipStation pulls instead of
 * being pushed to, so the CP **Push to {integration}** button is repurposed as "stamp the
 * shipment as bound to this integration so the shipnotify webhook can resolve it later."
 */
class ShipmentsProvider extends Provider
{
	public const ACTION_EXPORT = 'export';

	public const ACTION_SHIPNOTIFY = 'shipnotify';

	public ?string $username = null;

	public ?string $password = null;

	/**
	 * Restrict the export to shipments at this fulfillment status. Empty = no filter.
	 */
	public ?string $exportFulfillmentStatus = FulfillmentStatus::Open->value;

	/**
	 * Fulfillment-axis transition applied when ShipStation posts shipnotify. Empty = don't
	 * transition (only update tracking fields).
	 */
	public ?string $shippedFulfillmentCode = FulfillmentStatus::Fulfilled->value;

	/**
	 * Shipping-axis transition applied when ShipStation posts shipnotify. Empty = don't
	 * transition (only update tracking fields). Defaults to `in_transit` because a ShipStation
	 * shipnotify means the carrier has been handed the package and tracking is live.
	 */
	public ?string $shippedShippingCode = ShippingStatus::InTransit->value;

	/**
	 * Whether the export should split parent-order tax across shipments and send the per-shipment
	 * portion to ShipStation. Off by default: tax belongs to the parent order, splitting it across
	 * shipments is a convenience, and finance teams reconciling ShipStation against Commerce
	 * usually want ShipStation to show $0 tax. Enable per integration when ShipStation labels need
	 * the value.
	 */
	public bool $sendTax = false;

	/**
	 * Whether the export should split parent-order shipping cost across shipments and send the
	 * per-shipment portion to ShipStation. Off by default for the same reason as `sendTax`:
	 * shipping cost is a parent-order figure, and ShipStation typically pulls real carrier cost
	 * from the rate it purchases for the label.
	 */
	public bool $sendShipping = false;

	/**
	 * Whether the export should split parent-order discount across shipments and send the
	 * per-shipment portion to ShipStation as a synthesized adjustment line item. Off by default
	 * because discounts apply to the parent order; reporting them per shipment is a convenience.
	 * Enable when ShipStation labels need the post-discount net.
	 */
	public bool $sendDiscount = false;

	public static function displayName(): string
	{
		return Craft::t('shipstationconnect', 'provider.displayName');
	}

	public function sendShipment(Shipment $shipment, Order $order): void
	{
		/** @var ShipmentsPlugin $plugin */
		$plugin = ShipmentsPlugin::getInstance();
		$plugin->getIntegrationReferences()
			->setIntegrationReference($shipment, (string) $this->handle, (string) $shipment->id);
	}

	/**
	 * ShipStation custom store has no remote cancel API; the next pull reflects the local status,
	 * so cancellation is a local-only operation.
	 */
	public function cancelShipment(Shipment $shipment, Order $order): void
	{
	}

	public function canReceiveUpdates(): bool
	{
		return true;
	}

	public function export(Request $request): Response
	{
		$this->assertAuthorized($request);

		$exportQuery = ShipmentExportQuery::fromRequest($request);
		if ($exportQuery->statusHandle === null && ($this->exportFulfillmentStatus ?? '') !== '') {
			$exportQuery->statusHandle = $this->exportFulfillmentStatus;
		}

		/** @var ShipmentsPlugin $shipmentsPlugin */
		$shipmentsPlugin = ShipmentsPlugin::getInstance();
		$result = $shipmentsPlugin->getShipments()->findForExport($exportQuery);

		/** @var ShipStationConnectPlugin $shipStationConnectPlugin */
		$shipStationConnectPlugin = ShipStationConnectPlugin::getInstance();
		$xmlString = $shipStationConnectPlugin->getXml()->generateXmlForShipments(
			$result->shipments,
			max($result->pageCount, 1),
			$this,
		);

		/** @var Response $response */
		$response = Craft::$app->getResponse();
		$response->format = Response::FORMAT_RAW;
		$response->content = $xmlString;
		$response->headers->set('Content-Type', 'text/xml');
		return $response;
	}

	public function receiveShipmentUpdate(Request $request): ?Shipment
	{
		$this->assertAuthorized($request);

		$reference = $this->bodyString($request, 'order_number');
		if ($reference === null) {
			throw new PermanentIntegrationException('Missing order_number on shipnotify.');
		}

		/** @var ShipmentsPlugin $plugin */
		$plugin = ShipmentsPlugin::getInstance();
		$shipment = $plugin->getShipments()->findOneByReference($reference);
		if (! $shipment instanceof Shipment) {
			throw new PermanentIntegrationException("No shipment found for reference “{$reference}”.", 404);
		}

		$payload = new ShipmentUpdatePayload();
		$payload->setAttributes([
			'carrier' => $this->bodyString($request, 'carrier'),
			'service' => $this->bodyString($request, 'service'),
			'trackingNumber' => $this->bodyString($request, 'tracking_number'),
			'dateScheduledShip' => $this->bodyString($request, 'ship_date'),
		], true);

		if (($this->shippedFulfillmentCode ?? '') !== '') {
			$payload->targetFulfillmentCode = $this->shippedFulfillmentCode;
			$payload->fulfillmentStatusMessage = Craft::t('shipstationconnect', 'provider.shipnotifyWebhookMessage');
		}

		if (($this->shippedShippingCode ?? '') !== '') {
			$payload->targetShippingCode = $this->shippedShippingCode;
			$payload->shippingStatusMessage = Craft::t('shipstationconnect', 'provider.shipnotifyWebhookMessage');
		}

		if (! $payload->validate()) {
			throw new PermanentIntegrationException('Invalid shipnotify payload: ' . Json::encode($payload->getErrors()));
		}

		$source = $plugin->getIntegrations()->getIntegrationByHandle((string) $this->handle);

		$plugin->getShipments()->applyUpdate($shipment, $payload, null, $source, 'shipnotify');

		return $shipment;
	}

	public function getSettingsHtml(): ?string
	{
		return Craft::$app->getView()->renderTemplate(
			'shipstationconnect/_cp/providers/shipments/settings',
			[
				'provider' => $this,
				'fulfillmentCases' => self::enumOptions(FulfillmentStatus::cases()),
				'shippingCases' => self::enumOptions(ShippingStatus::cases()),
			],
			View::TEMPLATE_MODE_CP,
		);
	}

	/**
	 * HTTP basic auth check shared by export + shipnotify. Falls back to
	 * the global ShipStation Connect plugin settings when the per-integration
	 * credentials are unset, so a single set of credentials can drive both
	 * the legacy Commerce-orders flow and the new shipments flow.
	 *
	 * @throws UnauthorizedHttpException
	 * @throws PermanentIntegrationException
	 */
	protected function assertAuthorized(Request $request): void
	{
		[$expectedUsername, $expectedPassword] = $this->getExpectedCredentials();

		[$providedUsername, $providedPassword] = $request->getAuthCredentials();

		if (! is_string($providedUsername) || ! is_string($providedPassword)) {
			throw $this->challengeAuth('Missing HTTP basic credentials.');
		}

		if (! hash_equals($expectedUsername, $providedUsername) || ! hash_equals($expectedPassword, $providedPassword)) {
			throw $this->challengeAuth('Invalid ShipStation username or password.');
		}
	}

	/**
	 * @return array<array-key, mixed>
	 */
	protected function defineRules(): array
	{
		return array_merge(parent::defineRules(), [
			[['username', 'password'],
				'string',
				'skipOnEmpty' => true],
			[['exportFulfillmentStatus', 'shippedFulfillmentCode'],
				'in',
				'range' => self::enumValuesWithEmpty(FulfillmentStatus::cases())],
			[['shippedShippingCode'],
				'in',
				'range' => self::enumValuesWithEmpty(ShippingStatus::cases())],
			[['sendTax', 'sendShipping', 'sendDiscount'], 'boolean'],
		]);
	}

	private function bodyString(Request $request, string $name): ?string
	{
		$value = $request->getBodyParam($name);
		if (! is_string($value) || $value === '') {
			return null;
		}

		return $value;
	}

	/**
	 * Resolve the username + password ShipStation should be sending. Per-integration values win
	 * when present and non-empty; otherwise fall back to the global plugin settings. Env-var
	 * references are resolved via `App::parseEnv`.
	 *
	 * @return array{string, string}
	 * @throws PermanentIntegrationException
	 */
	private function getExpectedCredentials(): array
	{
		/** @var ShipStationConnectPlugin $shipStationConnectPlugin */
		$shipStationConnectPlugin = ShipStationConnectPlugin::getInstance();
		$settings = $shipStationConnectPlugin->settings;

		$username = ($this->username ?? '') !== '' ? $this->username : $settings->shipstationUsername;
		$password = ($this->password ?? '') !== '' ? $this->password : $settings->shipstationPassword;

		$expectedUsername = (string) App::parseEnv($username);
		$expectedPassword = (string) App::parseEnv($password);

		if ($expectedUsername === '' || $expectedPassword === '') {
			throw new PermanentIntegrationException('ShipStation credentials are not configured.', 500);
		}

		return [$expectedUsername, $expectedPassword];
	}

	private function challengeAuth(string $message): UnauthorizedHttpException
	{
		/** @var Response $response */
		$response = Craft::$app->getResponse();
		$response->getHeaders()->set('WWW-Authenticate', 'Basic realm="ShipStation Connect"');

		return new UnauthorizedHttpException($message);
	}

	/**
	 * Backed-enum `value`s plus an empty-string sentinel, suitable as the `range` for an `in`
	 * validator on optional enum-backed columns.
	 *
	 * @param list<BackedEnum> $cases
	 * @return list<string>
	 */
	private static function enumValuesWithEmpty(array $cases): array
	{
		return array_merge([''], array_map(static fn (BackedEnum $case): string => (string) $case->value, $cases));
	}

	/**
	 * Build `{label, value}` option rows for a status enum, for the settings template's select fields.
	 *
	 * @param list<FulfillmentStatus|ShippingStatus> $cases
	 * @return list<array{label: string, value: string}>
	 */
	private static function enumOptions(array $cases): array
	{
		return array_map(
			static fn (FulfillmentStatus|ShippingStatus $case): array => [
				'label' => $case->label(),
				'value' => $case->value,
			],
			$cases,
		);
	}
}
