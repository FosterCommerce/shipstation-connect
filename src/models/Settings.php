<?php
namespace fostercommerce\shipstationconnect\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    public $storesFieldHandle = '';
    public $shipstationUsername = '';
    public $shipstationPassword = '';
    public $ordersPageSize = 25;
    public $orderIdPrefix = '';
    public $matrixFieldHandle = 'shippingInfo';
    public $blockTypeHandle = 'shippingInfo';
    public $carrierFieldHandle = 'carrier';
    public $serviceFieldHandle = 'service';
    public $trackingNumberFieldHandle = 'trackingNumber';
    public $billingSameAsShipping = false;
}
