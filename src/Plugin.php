<?php
namespace fostercommerce\shipstationconnect;

use Craft;
use yii\base\Exception;

class Plugin extends \craft\base\Plugin
{
    public $hasCpSettings = true;
    public $schemaVersion = '1.0.1';

    public function init()
    {
        parent::init();

        $this->setComponents([
            'xml' => \fostercommerce\shipstationconnect\services\Xml::class,
        ]);
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
