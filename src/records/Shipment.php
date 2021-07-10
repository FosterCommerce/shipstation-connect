<?php

namespace fostercommerce\shipstationconnect\records;

use modules\sitemodule\SiteModule;

use Craft;
use craft\db\ActiveRecord;

/**
 * Parial Quantity Record
 *
 * @property int $orderId
 * @property int $lineItemId
 * @property int $qty
 */
class Shipment extends ActiveRecord
{
    const TABLE = '{{%shipstationconnect_shipments}}';

    // Public Methods
    // =========================================================================

	/**
	 * @inheritdoc
	 */
    public static function tableName(): string
    {
        return self::TABLE;
    }
}
