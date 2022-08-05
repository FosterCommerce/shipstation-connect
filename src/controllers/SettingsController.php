<?php
namespace fostercommerce\shipstationconnect\controllers;

use Craft;
use craft\web\Controller;
use craft\db\Query;
use fostercommerce\shipstationconnect\Plugin;
use fostercommerce\shipstationconnect\models\Settings;

use yii\web\Response;

class SettingsController extends Controller
{

    public function actionIndex(): Response
    {
        $plugin = Plugin::getInstance();
        return $this->renderTemplate("shipstationconnect/settings/index", [
            'settings' => $plugin->settings,
            'isUsingCraftAuth' => $plugin->isAuthHandledByCraft(),
        ]);
    }

    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $postData = Craft::$app->getRequest()->getBodyParam('settings');
        $settings = new Settings($postData);

        if (!$settings->validate() || !Craft::$app->getPlugins()->savePluginSettings(Plugin::getInstance(), $settings->toArray())) {
            Craft::$app->getSession()->setError(Craft::t('shipstationconnect', 'Couldn’t save settings.'));
            return $this->renderTemplate('shipstationconnect/settings/index', ['settings' => $settings]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('shipstationconnect', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
