<?php

declare(strict_types=1);

namespace fostercommerce\shipstationconnect\models;

use craft\commerce\elements\Order as CommerceOrder;
use Money\Money;

/**
 * Per-shipment payload assembled by `Xml::generateXmlForShipments` and consumed by
 * `Order::fromShipment`. Bundles the parent-derived context (commerce order, customer,
 * payment method) with the shipment's own line items and pre-allocated Money totals.
 *
 * Discount/tax/shipping arrive as Money objects so accumulation precision is preserved
 * up to the XML-serialization boundary.
 */
class ShipmentExportContext
{
	/**
	 * @param list<Item> $items
	 */
	public function __construct(
		public readonly CommerceOrder $commerceOrder,
		public readonly array $items,
		public readonly Money $subtotal,
		public readonly Money $discount,
		public readonly Money $tax,
		public readonly Money $shipping,
		public readonly ?string $paymentMethod,
		public readonly ?Customer $customer,
	) {
	}
}
