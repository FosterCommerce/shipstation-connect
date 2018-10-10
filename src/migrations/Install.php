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

class Install extends Migration
{
    public function safeUp()
    {
        $fieldService = Craft::$app->fields;

        $matrixHandle = 'shippingInfo';
        // Check for existing matrix
        if ($fieldService->getFieldByHandle($matrixHandle)) {
            return;
        }

        // Create a new group or fetch existing one
        $group = new FieldGroup();
        $group->name = Plugin::getInstance()->name;
        if (!$fieldService->saveGroup($group, true)) {
            // Group may already exist, try find it
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
                // Validation has failed, and the group does not exist.
                Craft::error(Craft::t(
                    'shipstationconnect',
                    'FieldGroup validation failed for {groupName}.',
                    ['groupName' => $group->name]
                ));
                return;
            }
        }

        // The field doesn't exist, create it
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

        // Add to Orders FieldLayout
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

            if (!$tab) {
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

    public function safeDown()
    {
    }
}
