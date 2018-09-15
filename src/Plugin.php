<?php
namespace fostercommerce\shipstationconnect;

use Craft;

class Plugin extends \craft\base\Plugin
{
    public $hasCpSettings = true;

    public function init()
    {
        parent::init();

        $this->setComponents([
            'xml' => \fostercommerce\shipstationconnect\services\Xml::class,
        ]);
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
