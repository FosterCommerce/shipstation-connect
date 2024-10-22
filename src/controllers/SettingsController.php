<?php

namespace fostercommerce\shipstationconnect\controllers;

use Craft;
use craft\errors\MissingComponentException;
use craft\web\Application;
use craft\web\Controller;
use fostercommerce\shipstationconnect\models\Settings;
use fostercommerce\shipstationconnect\Plugin;

use yii\web\BadRequestHttpException;
use yii\web\Response;

class SettingsController extends Controller
{
	public function actionIndex(): Response
	{
		$plugin = Plugin::getInstance();
		return $this->renderTemplate('shipstationconnect/settings/index', [
			'settings' => $plugin?->settings,
			'isUsingCraftAuth' => $plugin?->isAuthHandledByCraft(),
		]);
	}

	/**
	 * @throws MissingComponentException
	 * @throws BadRequestHttpException
	 */
	public function actionSave(): Response
	{
		$this->requirePostRequest();
		/** @var Application $app */
		$app = Craft::$app;

		/** @var array<string, mixed> $postData */
		$postData = $app->getRequest()->getBodyParam('settings');
		$settings = new Settings($postData);

		$plugin = Plugin::getInstance();
		if ($plugin === null) {
			throw new \RuntimeException('Plugin not available');
		}

		if (! $settings->validate() || ! Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray())) {
			Craft::$app->getSession()->setError(Craft::t('shipstationconnect', 'Couldn’t save settings.'));
			return $this->renderTemplate('shipstationconnect/settings/index', [
				'settings' => $settings,
			]);
		}

		Craft::$app->getSession()->setNotice(Craft::t('shipstationconnect', 'Settings saved.'));

		return $this->redirectToPostedUrl();
	}
}
