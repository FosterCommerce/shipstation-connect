<?php

namespace fostercommerce\shipstationconnect\events;

use craft\base\Event;
use fostercommerce\shipstationconnect\models\Order;

class OrderEvent extends Event
{
	/**
	 * The order that has been transformed into a format ready to be exported to ShipStation
	 */
	public Order $transformedOrder;
}
