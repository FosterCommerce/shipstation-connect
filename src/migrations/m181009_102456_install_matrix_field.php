<?php

namespace fostercommerce\shipstationconnect\migrations;

use Craft;
use craft\db\Migration;
use craft\base\Field;
use craft\models\FieldGroup;
use craft\models\MatrixBlockType;
use craft\models\FieldLayoutTab;
use craft\fields\PlainText;
use craft\fields\Matrix;
use craft\commerce\Plugin as CommercePlugin;
use fostercommerce\shipstationconnect\Plugin;

/**
 * m181009_102456_install_matrix_field migration.
 */
class m181009_102456_install_matrix_field extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $fieldService = Craft::$app->fields;

        $matrixHandle = 'shippingInfo';
        if ($fieldService->getFieldByHandle($matrixHandle)) {
            return;
        }

        $group = new FieldGroup();
        $group->name = Plugin::getInstance()->name;
        if (!$fieldService->saveGroup($group, true)) {
            $groups = array_filter(
                $fieldService->getAllGroups(),
                function ($g) use ($group) {
                    return (strtolower($g->name) === strtolower($group->name));
                }
            );
            $found = array_shift($groups);
            if ($found) {
                $group = $found;
            } else {
                Craft::error(Craft::t(
                    'shipstationconnect',
                    'FieldGroup validation failed for {groupName}.',
                    ['groupName' => $group->name]
                ));
                return;
            }
        }

        $carrier = $fieldService->createField([
            'type' => PlainText::class,
            'name' => 'Carrier',
            'handle' => 'carrier',
        ]);

        $service = $fieldService->createField([
            'type' => PlainText::class,
            'name' => 'Service',
            'handle' => 'service',
        ]);

        $tracking = $fieldService->createField([
            'type' => PlainText::class,
            'name' => 'Tracking Number',
            'handle' => 'tracking',
        ]);

        $block = new MatrixBlockType([
            'name' => 'Shipping Info',
            'handle' => 'shippingInfo',
            'fields' => [
                $carrier,
                $service,
                $tracking,
            ]
        ]);

        $matrix = $fieldService->createField([
            'type' => Matrix::class,
            'name' => 'Shipping Info',
            'handle' => $matrixHandle,
            'groupId' => $group->id,
            'translationMethod' => Field::TRANSLATION_METHOD_NONE,
            'blockTypes' => [$block],
            'maxBlocks' => 1,
        ]);

        if (!$fieldService->saveField($matrix)) {
            Craft::error(Craft::t(
                'shipstationconnect',
                'Failed to create {fieldName} Matrix.',
                ['fieldName' => $matrix->name]
            ));
            return;
        }

        $commerceOrderSettings = CommercePlugin::getInstance()->orderSettings;
        $orderSettings = $commerceOrderSettings->getOrderSettingByHandle('order');
        if ($orderSettings) {
            $fieldLayout = $orderSettings->getFieldLayout();

            $currentTabs = $fieldLayout->getTabs();

            $tabName = Plugin::getInstance()->name;

            $tabs = array_filter($currentTabs, function ($tab) use ($tabName) {
                return strtolower($tab->name) === strtolower($tabName);
            });
            $tab = array_shift($tabs);

            if(!$tab) {
                $tab = new FieldLayoutTab([
                    'name' => $tabName,
                    'sortOrder' => count($currentTabs),
                    'fields' => [
                        $matrix,
                    ]
                ]);

                array_push($currentTabs, $tab);
                $fieldLayout->setTabs($currentTabs);

                $fieldService->saveLayout($fieldLayout);

                $orderSettings->fieldLayoutId = $fieldLayout->id;
                $commerceOrderSettings->saveOrderSetting($orderSettings);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181009_102456_install_matrix_field cannot be reverted.\n";
        return false;
    }
}
