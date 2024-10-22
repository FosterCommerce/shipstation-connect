<?php

namespace fostercommerce\shipstationconnect\events;

use craft\commerce\elements\Order;
use yii\base\Event;

class FindOrderEvent extends Event
{
	public string $orderNumber;

	public ?Order $order = null;
}
