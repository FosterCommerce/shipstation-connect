<?php

declare(strict_types=1);

namespace fostercommerce\shipstationconnect\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use fostercommerce\shipments\errors\IntegrationException;
use fostercommerce\shipments\errors\PermanentIntegrationException;
use fostercommerce\shipments\Plugin as ShipmentsPlugin;
use fostercommerce\shipstationconnect\providers\ShipmentsProvider;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;
use yii\web\UnauthorizedHttpException;

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
	public function actionProcess(string $integrationHandle): Response
	{
		$action = (string) $this->request->getQueryParam('action');
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
	 * Translate a `PermanentIntegrationException` into the matching `HttpException` subclass
	 * by sniffing `getCode()`. The Shipments plugin documents the 404/400 convention on
	 * `Integrations::resolveEnabledProvider`; unknown codes fall through to 400 with a
	 * warning so a future provider code addition surfaces in the log.
	 */
	private function mapPermanentException(PermanentIntegrationException $permanentException): HttpException
	{
		$message = $permanentException->getMessage();
		$code = $permanentException->getCode();

		return match ($code) {
			400 => new BadRequestHttpException($message, 0, $permanentException),
			401 => new UnauthorizedHttpException($message, 0, $permanentException),
			403 => new ForbiddenHttpException($message, 0, $permanentException),
			404 => new NotFoundHttpException($message, 0, $permanentException),
			405 => new MethodNotAllowedHttpException($message, 0, $permanentException),
			500 => new ServerErrorHttpException($message, 0, $permanentException),
			default => $this->fallbackBadRequest($message, $code, $permanentException),
		};
	}

	private function fallbackBadRequest(string $message, int $code, PermanentIntegrationException $permanentException): BadRequestHttpException
	{
		Craft::warning(
			sprintf('Unmapped PermanentIntegrationException code %d; mapping to 400. Message: %s', $code, $message),
			'shipstationconnect',
		);

		return new BadRequestHttpException($message, 0, $permanentException);
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
