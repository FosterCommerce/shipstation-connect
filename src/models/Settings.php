<?php
namespace fostercommerce\shipstationconnect\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    public $shipstationUsername = '';
    public $shipstationPassword = '';
    public $ordersPageSize = 25;
    public $orderIdPrefix = '';
}
