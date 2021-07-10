<?php

namespace fostercommerce\shipstationconnect\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;
use craft\commerce\db\Table as CommerceTable;
use fostercommerce\shipstationconnect\records\Shipment;

/**
 * m210428_235053_shipments migration.
 */
class m210428_235053_shipments extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable(Shipment::TABLE, [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'shipmentId' => $this->integer()->notNull(),
            'shippedQtys' => $this->longText(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createIndex(null, Shipment::TABLE, 'orderId', false);
        $this->createIndex(null, Shipment::TABLE, 'shipmentId', true);

        $this->addForeignKey(null, Shipment::TABLE, ['orderId'], CommerceTable::ORDERS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Shipment::TABLE, ['shipmentId'], Table::MATRIXBLOCKS, ['id'], 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210428_235053_shipments cannot be reverted.\n";
        return false;
    }
}
