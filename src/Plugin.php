<?php

namespace fostercommerce\shipstationconnect;

use Craft;
use craft\base\Model;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use fostercommerce\shipstationconnect\models\Settings;
use fostercommerce\shipstationconnect\services\Xml;
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

	public function init(): void
	{
		parent::init();

		$this->setComponents([
			'xml' => Xml::class,
		]);

		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_CP_URL_RULES,
			function (RegisterUrlRulesEvent $event): void {
				$event->rules['shipstationconnect/settings'] = 'shipstationconnect/settings/index';
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

	public function isAuthHandledByCraft(): bool
	{
		// RE https://github.com/craftcms/cms/issues/6421, if the site has the
		// `enableBasicHttpAuth` setting set to true, we can assume that Craft
		// will handle the authentication of requests.
		if (version_compare(Craft::$app->getVersion(), '3.5.0') < 0) {
			return false;
		}

		return (bool) Craft::$app->getConfig()->getGeneral()->enableBasicHttpAuth;
	}

	protected function settingsHtml(): ?string
	{
		return Craft::$app->getView()->renderTemplate('shipstationconnect/settings', [
			'settings' => $this->getSettings(),
		]);
	}

	protected function beforeInstall(): void
	{
		if (! Craft::$app->plugins->isPluginInstalled('commerce') || ! Craft::$app->plugins->isPluginEnabled('commerce')) {
			Craft::error(Craft::t(
				'shipstationconnect',
				'Failed to install. Craft Commerce is required.'
			));
		}
	}

	protected function createSettingsModel(): ?Model
	{
		return new Settings();
	}
}
