<?php

declare(strict_types=1);

namespace fostercommerce\shipstationconnect\controllers;

use Craft;
use craft\web\Controller;
use fostercommerce\shipments\errors\IntegrationException;
use fostercommerce\shipments\errors\PermanentIntegrationException;
use fostercommerce\shipments\Plugin as ShipmentsPlugin;
use fostercommerce\shipstationconnect\providers\ShipmentsProvider;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Single-URL custom-store endpoint for the Shipments-plugin provider. ShipStation only
 * accepts one URL per store, so this controller dispatches `?action=export` and
 * `?action=shipnotify` to the same provider that the Shipments plugin's own
 * `shipments/exports/<handle>` and `shipments/webhooks/<handle>` routes call.
 */
class ShipmentsStoreController extends Controller
{
	public $enableCsrfValidation = false;

	protected array|int|bool $allowAnonymous = true;

	/**
	 * @throws HttpException
	 */
	public function actionProcess(string $integrationHandle, ?string $action = null): Response
	{
		if ($action !== ShipmentsProvider::ACTION_EXPORT && $action !== ShipmentsProvider::ACTION_SHIPNOTIFY) {
			throw new BadRequestHttpException('Invalid action');
		}

		/** @var ShipmentsPlugin $shipmentsPlugin */
		$shipmentsPlugin = ShipmentsPlugin::getInstance();

		try {
			$provider = $this->resolveProvider($shipmentsPlugin, $integrationHandle);

			return $action === ShipmentsProvider::ACTION_EXPORT
				? $provider->export($this->request)
				: $this->dispatchShipnotify($provider);
		} catch (PermanentIntegrationException $permanentIntegrationException) {
			Craft::error(sprintf('[%s] %s', $integrationHandle, $permanentIntegrationException->getMessage()), 'shipstationconnect');
			throw $this->mapPermanentException($permanentIntegrationException);
		} catch (IntegrationException $integrationException) {
			Craft::warning(sprintf('[%s] transient: %s', $integrationHandle, $integrationException->getMessage()), 'shipstationconnect');
			throw new ServerErrorHttpException($integrationException->getMessage(), 0, $integrationException);
		} catch (Throwable $throwable) {
			if ($throwable instanceof HttpException) {
				throw $throwable;
			}

			Craft::error(sprintf('[%s] unexpected error: %s', $integrationHandle, $throwable->getMessage()), 'shipstationconnect');
			throw new ServerErrorHttpException('Request failed.', 0, $throwable);
		}
	}

	/**
	 * Map a `PermanentIntegrationException` to an HTTP status, matching the Shipments plugin's own
	 * `ExportsController`/`WebhooksController` convention: 404 for an unknown/unbound handle, 400
	 * for everything else.
	 */
	private function mapPermanentException(PermanentIntegrationException $permanentException): HttpException
	{
		$message = $permanentException->getMessage();

		return $permanentException->getCode() === 404
			? new NotFoundHttpException($message, 0, $permanentException)
			: new BadRequestHttpException($message, 0, $permanentException);
	}

	/**
	 * Resolves the integration handle to a ShipStation provider. Lets `PermanentIntegrationException`
	 * propagate so the action's central catch logs and maps it.
	 */
	private function resolveProvider(ShipmentsPlugin $shipmentsPlugin, string $integrationHandle): ShipmentsProvider
	{
		$provider = $shipmentsPlugin->getIntegrations()->resolveEnabledProvider($integrationHandle);

		if (! $provider instanceof ShipmentsProvider) {
			throw new NotFoundHttpException("Integration {$integrationHandle} is not bound to a ShipStation provider.");
		}

		return $provider;
	}

	private function dispatchShipnotify(ShipmentsProvider $provider): Response
	{
		$this->requirePostRequest();
		$shipment = $provider->receiveShipmentUpdate($this->request);

		return $this->asJson([
			'success' => true,
			'shipmentId' => $shipment?->id,
		]);
	}
}
