<?php
namespace fostercommerce\shipstationconnect;

use Craft;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;
use yii\base\Exception;
use fostercommerce\shipstationconnect\web\twig\filters\IsFieldTypeFilter;

class Plugin extends \craft\base\Plugin
{
    public $hasCpSettings = true;
    public $hasCpSection = true;
    public $schemaVersion = '1.0.1';

    public function init()
    {
        parent::init();

        $this->setComponents([
            'xml' => \fostercommerce\shipstationconnect\services\Xml::class,
        ]);

        Craft::$app->view->registerTwigExtension(new IsFieldTypeFilter());

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['shipstationconnect/settings'] = 'shipstationconnect/settings/index';
                $event->rules['shipstationconnect/settings/save'] = 'shipstationconnect/settings/save';
            }
        );
    }

    protected function beforeInstall(): bool
    {
        if (!Craft::$app->plugins->isPluginInstalled('commerce')) {
            Craft::error(Craft::t(
                'shipstationconnect',
                'Failed to install. Craft Commerce is required.'
            ));
            return false;
        }

        if (!Craft::$app->plugins->isPluginEnabled('commerce')) {
            Craft::error(Craft::t(
                'shipstationconnect',
                'Failed to install. Craft Commerce is required.'
            ));
            return false;
        }

        return true;
    }

    public function getCpNavItem()
    {
        $item = parent::getCpNavItem();

        $item['label'] = Craft::t('shipstationconnect', 'ShipStation Connect');
        $item['subnav'] = [
            'open' => ['label' => Craft::t('shipstationconnect', 'Dashboard'), 'url' => 'shipstationconnect/open'],
            'settings' => ['label' => Craft::t('shipstationconnect', 'Settings'), 'url' => 'shipstationconnect/settings'],
        ];
        return $item;
    }

    protected function createSettingsModel()
    {
        return new \fostercommerce\shipstationconnect\models\Settings();
    }

    public function settingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('shipstationconnect/settings', [
            'settings' => $this->getSettings()
        ]);
    }
}
