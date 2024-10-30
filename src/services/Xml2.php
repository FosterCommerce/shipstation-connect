<?php

namespace fostercommerce\shipstationconnect\services;

use craft\base\Component;
use craft\commerce\elements\Order as CommerceOrder;
use fostercommerce\shipstationconnect\models\Order;
use Illuminate\Support\Collection;

class Xml2 extends Component
{
	/**
	 * @param CommerceOrder[] $commerceOrders
	 * @return Collection<int, Order>
	 */
	public function mapOrders(array $commerceOrders): Collection
	{
		return collect($commerceOrders)
			->map(Order::fromCommerceOrder(...));
	}
}
