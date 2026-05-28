<?php

namespace fostercommerce\shipstationconnect\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order as CommerceOrder;
use craft\commerce\models\LineItem as CommerceLineItem;
use craft\helpers\MoneyHelper;
use DOMDocument;
use DOMElement;
use fostercommerce\shipments\elements\Shipment;
use fostercommerce\shipstationconnect\events\OrderEvent;
use fostercommerce\shipstationconnect\models\Customer;
use fostercommerce\shipstationconnect\models\Item;
use fostercommerce\shipstationconnect\models\Order;
use fostercommerce\shipstationconnect\models\Orders;
use fostercommerce\shipstationconnect\models\ShipmentExportContext;
use fostercommerce\shipstationconnect\models\ShipmentItems;
use fostercommerce\shipstationconnect\Plugin;
use fostercommerce\shipstationconnect\providers\ShipmentsProvider;
use Illuminate\Support\Collection;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerBuilder;
use Money\Currency;
use Money\Money;
use yii\base\Event;

class Xml extends Component
{
	/**
	 * @var string
	 */
	public const ORDER_EVENT = 'orderEvent';

	/**
	 * @param CommerceOrder[] $commerceOrders
	 */
	public function generateXml(array $commerceOrders, int $pageCount): string
	{
		$orders = collect($commerceOrders)->map(Order::fromCommerceOrder(...));
		return $this->renderOrders($orders, $pageCount);
	}

	/**
	 * Build XML for a page of Shipments-plugin Shipments. Each Shipment becomes a ShipStation
	 * "order" via `Order::fromShipment`, using the shipment's parent Commerce order for
	 * customer/payment/shipping-method context.
	 *
	 * Discount, tax, and shipping allocation honor `$provider->sendDiscount`,
	 * `$provider->sendTax`, and `$provider->sendShipping`. When enabled, parent totals are
	 * split across the parent's shipments via `Money::allocate` so per-shipment cents always
	 * sum back to the parent total. When disabled, ShipStation sees `0` for that field on
	 * every shipment.
	 *
	 * Note: `Xml::ORDER_EVENT` fires once per generated Order, which means once per shipment.
	 * Subscribers written for the legacy single-order flow will see N events per parent.
	 *
	 * @param iterable<Shipment> $shipments
	 */
	public function generateXmlForShipments(iterable $shipments, int $pageCount, ShipmentsProvider $provider): string
	{
		$parentsById = [];
		$shipmentsByParent = [];
		foreach ($shipments as $shipment) {
			$parent = $shipment->getOrder();
			if (! $parent instanceof CommerceOrder) {
				Craft::warning(
					"Shipment {$shipment->id} has no resolvable parent order. Excluding from ShipStation export.",
					'shipstationconnect'
				);
				continue;
			}

			$parentsById[$parent->id] = $parent;
			$shipmentsByParent[$parent->id][] = $shipment;
		}

		$built = collect();
		foreach ($shipmentsByParent as $parentId => $parentShipments) {
			foreach ($this->buildOrdersForParent($parentsById[$parentId], $parentShipments, $provider) as $order) {
				$built->add($order);
			}
		}

		return $this->renderOrders($built, $pageCount);
	}

	/**
	 * Build a ShipStation Order per shipment under one parent. Discount, tax, and shipping are
	 * allocated across the parent's shipments via `Money::allocate` when the corresponding
	 * provider toggle is on; cents always sum back to the parent total. Toggles off skip the
	 * allocation entirely (zero per shipment).
	 *
	 * @param list<Shipment> $shipments
	 * @return list<Order>
	 */
	private function buildOrdersForParent(CommerceOrder $parent, array $shipments, ShipmentsProvider $provider): array
	{
		/** @var Money $zeroMoney */
		$zeroMoney = MoneyHelper::toMoney([
			'value' => '0',
			'currency' => $parent->getPaymentCurrency(),
		]);
		$currency = $zeroMoney->getCurrency();

		$lineItemsById = [];
		foreach ($parent->lineItems as $lineItem) {
			$lineItemsById[(int) $lineItem->id] = $lineItem;
		}

		$customer = Customer::fromCommerceOrder($parent);
		$paymentMethod = $parent->getPaymentSource()?->description;

		$itemsByIndex = [];
		$subtotals = [];
		foreach ($shipments as $index => $shipment) {
			$built = $this->buildShipmentItems($shipment, $lineItemsById, $currency, $zeroMoney);
			$itemsByIndex[$index] = $built->items;
			$subtotals[$index] = $built->subtotal;
		}

		$discountAllocations = $this->allocateParentTotal(
			$subtotals,
			$provider->sendDiscount ? (string) $parent->getTotalDiscount() : '0',
			$currency,
			$zeroMoney,
		);

		$taxAllocations = $this->allocateParentTotal(
			$subtotals,
			$provider->sendTax ? (string) $parent->getTotalTax() : '0',
			$currency,
			$zeroMoney,
		);

		$shippingAllocations = $this->allocateParentTotal(
			$subtotals,
			$provider->sendShipping ? (string) $parent->getTotalShippingCost() : '0',
			$currency,
			$zeroMoney,
		);

		$orders = [];
		foreach ($shipments as $index => $shipment) {
			$orders[] = Order::fromShipment($shipment, new ShipmentExportContext(
				commerceOrder: $parent,
				items: $itemsByIndex[$index],
				subtotal: $subtotals[$index],
				discount: $discountAllocations[$index],
				tax: $taxAllocations[$index],
				shipping: $shippingAllocations[$index],
				paymentMethod: $paymentMethod,
				customer: $customer,
			));
		}

		return $orders;
	}

	/**
	 * Walk a Shipment's line items, resolving each to a parent Commerce line item, building an
	 * `Item` and accumulating a Money subtotal. A line item that can't be resolved or priced is
	 * logged and skipped from both the items list and the subtotal so the two never disagree.
	 *
	 * @param array<int, CommerceLineItem> $lineItemsById
	 */
	private function buildShipmentItems(
		Shipment $shipment,
		array $lineItemsById,
		Currency $currency,
		Money $zeroMoney,
	): ShipmentItems {
		$subtotalMoney = $zeroMoney;
		$items = [];
		foreach ($shipment->getLineItems() as $shipmentLineItem) {
			$commerceLineItem = $lineItemsById[$shipmentLineItem->lineItemId] ?? null;
			if (! $commerceLineItem instanceof CommerceLineItem) {
				Craft::warning(
					"Shipment {$shipment->id} references line item {$shipmentLineItem->lineItemId} that is not on parent order {$shipment->orderId}. Skipping.",
					'shipstationconnect'
				);
				continue;
			}

			$unitPriceMoney = MoneyHelper::toMoney([
				'value' => (string) $commerceLineItem->salePrice,
				'currency' => $currency,
			]);
			if (! $unitPriceMoney instanceof Money) {
				Craft::warning(
					"Shipment {$shipment->id} line item {$commerceLineItem->id} salePrice could not be resolved to Money. Skipping.",
					'shipstationconnect'
				);
				continue;
			}

			$items[] = Item::fromCommerceLineItem($commerceLineItem, $shipmentLineItem->qty);
			$subtotalMoney = $subtotalMoney->add($unitPriceMoney->multiply((string) $shipmentLineItem->qty));
		}

		return new ShipmentItems($items, $subtotalMoney);
	}

	/**
	 * Pro-rata `Money::allocate` keyed by per-shipment subtotal. Returns zero per shipment
	 * when the parent total is zero or every subtotal is zero, so callers can rely on a
	 * one-Money-per-subtotal return regardless of input shape.
	 *
	 * @param list<Money> $subtotals
	 * @return list<Money>
	 */
	private function allocateParentTotal(array $subtotals, string $amount, Currency $currency, Money $zeroMoney): array
	{
		/** @var Money $totalMoney */
		$totalMoney = MoneyHelper::toMoney([
			'value' => $amount,
			'currency' => $currency,
		]);

		$ratios = array_map(static fn (Money $subtotal): int => (int) $subtotal->getAmount(), $subtotals);
		if ($ratios === [] || $totalMoney->isZero() || array_sum($ratios) === 0) {
			return array_fill(0, count($subtotals), $zeroMoney);
		}

		return $totalMoney->allocate($ratios);
	}

	/**
	 * Shared validation + serialization path for both order- and shipment-derived collections.
	 *
	 * @param Collection<int, Order> $orders
	 */
	private function renderOrders(Collection $orders, int $pageCount): string
	{
		/** @var Collection<int, Order> $failed */
		[$valid, $failed] = $orders
			->filter(static function (Order $order): bool {
				$items = $order->getItems();
				if ($items === []) {
					Craft::warning(
						"Order {$order->getOrderId()} found with no line items. Excluding from ShipStation export.",
						'shipstationconnect'
					);
					return false;
				}

				return true;
			})
			->map(static function (Order $order): Order {
				$orderEvent = new OrderEvent([
					'order' => $order,
				]);
				Event::trigger(static::class, self::ORDER_EVENT, $orderEvent);
				return $orderEvent->order;
			})
			->reduceSpread(static function (Collection $valid, Collection $failed, Order $order): array {
				/** @var Collection<int, Order> $valid */
				/** @var Collection<int, Order> $failed */

				if (! $order->validate()) {
					$failed->add($order);
				} else {
					$valid->add($order);
				}

				return [$valid, $failed];
			}, collect(), collect());

		$ordersWrapper = Orders::fromCollection($valid, $pageCount);

		if ($failed->isNotEmpty()) {
			/** @var Order $firstFailedOrder */
			$firstFailedOrder = $failed->first();
			$firstErrrors = $firstFailedOrder->getFirstErrors();
			$attribute = key($firstErrrors);
			$keys = array_keys($firstErrrors);
			$value = $firstErrrors[$keys[0]][0] ?? 'Unknown validation error';
			if (is_array($value)) {
				// Weird nested array of validation errors
				$value = $value[0];
			}

			if (Plugin::getInstance()?->settings->failOnValidation ?? false) {
				throw new \RuntimeException("Invalid Order ID {$firstFailedOrder->getOrderId()}: {$attribute} - {$value}");
			}

			Craft::error("Invalid Order ID {$firstFailedOrder->getOrderId()}: {$attribute} - {$value}", 'shipstationconnect');
		}

		$serializer = SerializerBuilder::create()->build();
		$serializationContext = SerializationContext::create()->setGroups(['export']);

		$xmlString = $serializer->serialize($ordersWrapper, 'xml', $serializationContext);

		// There doesn't seem to be a way to set an attribute on the root node
		// This is a work-around.
		$dom = new DOMDocument();
		$dom->loadXML($xmlString);
		/** @var DOMElement $root */
		$root = $dom->documentElement;
		$root->setAttribute('pages', (string) $pageCount);

		$xmlString = $dom->saveXML();
		if ($xmlString === false) {
			throw new \RuntimeException('Failed to export orders as XML');
		}

		return $xmlString;
	}
}
