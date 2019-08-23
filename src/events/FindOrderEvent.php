<?php
namespace fostercommerce\shipstationconnect\events;

use yii\base\Event;

class FindOrderEvent extends Event
{
    public $orderNumber;
    public $order;
}

