<?php
namespace fostercommerce\shipstationconnect;

use Craft;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use craft\services\UserPermissions;
use craft\events\RegisterUserPermissionsEvent;
use craft\base\Model;
use yii\base\Event;
use yii\base\Exception;
use fostercommerce\shipstationconnect\web\twig\filters\IsFieldTypeFilter;

class Plugin extends \craft\base\Plugin
{
    /**
     * @var		bool	$hasCpSettings
     */
    public bool $hasCpSettings = true;
    
    /**
     * @var		bool	$hasCpSection
     */
    public bool $hasCpSection = true;
    
    /**
     * @var		string	$schemaVersion
     */
    public string $schemaVersion = '1.0.1';

    
    /**
     * init.
     *
     * @author	Unknown
     * @since	v0.0.1
     * @version	v1.0.0	Monday, May 23rd, 2022.
     * @access	public
     * @return	void
     */
    public function init(): void
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

        Event::on(
            UserPermissions::class, 
            UserPermissions::EVENT_REGISTER_PERMISSIONS, 
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => 'ShipStation Connect',
                    'permissions' => [
                        'shipstationconnect-processOrders' => [
                                'label' => 'Process Orders'
                        ],
                    ]
                ];
            }
        );
    }

    protected function beforeInstall(): void
    {
        if (!Craft::$app->plugins->isPluginInstalled('commerce')) {
            Craft::error(Craft::t(
                'shipstationconnect',
                'Failed to install. Craft Commerce is required.'
            ));
            // return false;
        }

        if (!Craft::$app->plugins->isPluginEnabled('commerce')) {
            Craft::error(Craft::t(
                'shipstationconnect',
                'Failed to install. Craft Commerce is required.'
            ));
           // return false;
        }
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();

        $item['label'] = Craft::t('shipstationconnect', 'ShipStation Connect');
        $item['subnav'] = [
            'open' => ['label' => Craft::t('shipstationconnect', 'Dashboard'), 'url' => 'shipstationconnect/open'],
            'settings' => ['label' => Craft::t('shipstationconnect', 'Settings'), 'url' => 'shipstationconnect/settings'],
        ];
        return $item;
    }

    protected function createSettingsModel(): ?Model
    {
        return new \fostercommerce\shipstationconnect\models\Settings();
    }

    public function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('shipstationconnect/settings', [
            'settings' => $this->getSettings()
        ]);
    }

    public function isAuthHandledByCraft()
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
}
