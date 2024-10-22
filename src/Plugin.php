<?php

namespace fostercommerce\shipstationconnect;

use Craft;
use craft\base\Model;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\Application;
use craft\web\UrlManager;
use fostercommerce\shipstationconnect\models\Settings;
use fostercommerce\shipstationconnect\services\Xml;
use fostercommerce\shipstationconnect\web\twig\filters\IsFieldTypeFilter;
use yii\base\Event;

/**
 * @property-read Settings $settings
 * @property-read Xml $xml
 */
class Plugin extends \craft\base\Plugin
{
	public bool $hasCpSettings = true;

	public bool $hasCpSection = true;

	public string $schemaVersion = '1.0.1';

	/**
	 * init.
	 *
	 * @author	Unknown
	 * @since	v0.0.1
	 * @version	v1.0.0	Monday, May 23rd, 2022.
	 * @access	public
	 */
	public function init(): void
	{
		parent::init();

		$this->setComponents([
			'xml' => \fostercommerce\shipstationconnect\services\Xml::class,
		]);

		Craft::$app->view->registerTwigExtension(new IsFieldTypeFilter());

		// Because Shipstation uses a querystring parameter of 'action' in their requests.
		// This interferes with Craft's routing system.
		// So we intercept the request and rename that parameter to ssaction IF the request is for one of this plugin's controllers
		/*
		Craft::$app->on(Application::EVENT_INIT, function() {
			$request = Craft::$app->request;
			if(!$request->isConsoleRequest){
				if(in_array('actions', $request->getSegments()) && in_array('shipstationconnect', $request->getSegments())) {
					if(array_key_exists('action', $request->getQueryParams())) {
						// rename array key to match the action name
						$params = $request->getQueryParams();
						$params['ssaction'] = $params['action'];
						unset($params['action']);
						$request->setQueryParams($params);
					}
				};
			}
		});
		 */

		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_CP_URL_RULES,
			function (RegisterUrlRulesEvent $event) {
				$event->rules['shipstationconnect/settings'] = 'shipstationconnect/settings/index';
				$event->rules['shipstationconnect/settings/save'] = 'shipstationconnect/settings/save';
			}
		);


		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_SITE_URL_RULES,
			function (RegisterUrlRulesEvent $event) {
				$event->rules['export'] = 'shipstationconnect/orders/export';
			}
		);


		Event::on(
			UserPermissions::class,
			UserPermissions::EVENT_REGISTER_PERMISSIONS,
			function (RegisterUserPermissionsEvent $event) {
				$event->permissions[] = [
					'heading' => 'ShipStation Connect',
					'permissions' => [
						'shipstationconnect-processOrders' => [
							'label' => 'Process Orders',
						],
					],
				];
			}
		);
	}

	/**
	 * @return ?array{label: string, subnav: array<string, mixed>}
	 */
	public function getCpNavItem(): ?array
	{
		$item = parent::getCpNavItem();

		$item['label'] = Craft::t('shipstationconnect', 'ShipStation Connect');
		$item['subnav'] = [
			'open' => [
				'label' => Craft::t('shipstationconnect', 'Dashboard'),
				'url' => 'shipstationconnect/open',
			],
			'settings' => [
				'label' => Craft::t('shipstationconnect', 'Settings'),
				'url' => 'shipstationconnect/settings',
			],
		];
		return $item;
	}

	public function settingsHtml(): ?string
	{
		return Craft::$app->getView()->renderTemplate('shipstationconnect/settings', [
			'settings' => $this->getSettings(),
		]);
	}

	public function isAuthHandledByCraft(): bool
	{
		// RE https://github.com/craftcms/cms/issues/6421, if the site has the
		// `enableBasicHttpAuth` setting set to true, we can assume that Craft
		// will handle the authentication of requests.
		if (version_compare(Craft::$app->getVersion(), '3.5.0') >= 0) {
			if (Craft::$app->getConfig()->getGeneral()->enableBasicHttpAuth) {
				return true;
			}
		}

		return false;
	}

	protected function beforeInstall(): void
	{
		if (! Craft::$app->plugins->isPluginInstalled('commerce')) {
			Craft::error(Craft::t(
				'shipstationconnect',
				'Failed to install. Craft Commerce is required.'
			));
			// return false;
		}

		if (! Craft::$app->plugins->isPluginEnabled('commerce')) {
			Craft::error(Craft::t(
				'shipstationconnect',
				'Failed to install. Craft Commerce is required.'
			));
			// return false;
		}
	}

	protected function createSettingsModel(): ?Model
	{
		return new \fostercommerce\shipstationconnect\models\Settings();
	}
}
