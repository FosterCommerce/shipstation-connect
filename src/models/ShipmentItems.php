<?php

declare(strict_types=1);

namespace fostercommerce\shipstationconnect\models;

use Money\Money;

/**
 * One shipment's resolved line items plus their accumulated Money subtotal. The two are built
 * together by `Xml::buildShipmentItems` so they can never disagree: a line item skipped from
 * `items` is also skipped from `subtotal`.
 */
class ShipmentItems
{
	/**
	 * @param list<Item> $items
	 */
	public function __construct(
		public readonly array $items,
		public readonly Money $subtotal,
	) {
	}
}
