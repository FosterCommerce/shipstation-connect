<?php

namespace fostercommerce\shipstationconnect\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order as CommerceOrder;
use DOMDocument;
use DOMElement;
use fostercommerce\shipstationconnect\events\OrderEvent;
use fostercommerce\shipstationconnect\models\Order;
use fostercommerce\shipstationconnect\models\Orders;
use fostercommerce\shipstationconnect\Plugin;
use Illuminate\Support\Collection;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerBuilder;
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
		/** @var Collection<int, Order> $failed */
		[$orders, $failed] = collect($commerceOrders)
			->map(Order::fromCommerceOrder(...))
			->map(static function (Order $order): Order {
				$orderEvent = new OrderEvent([
					'order' => $order,
				]);
				Event::trigger(static::class, self::ORDER_EVENT, $orderEvent);
				return $orderEvent->order;
			})
			->reduceSpread(static function (Collection $orders, Collection $failed, Order $order): array {
				/** @var Collection<int, Order> $orders */
				/** @var Collection<int, Order> $failed */

				if (! $order->validate()) {
					$failed->add($order);
				} else {
					$orders->add($order);
				}

				return [$orders, $failed];
			}, collect(), collect());

		$orders = Orders::fromCollection($orders, $pageCount);

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

		$xmlString = $serializer->serialize($orders, 'xml', $serializationContext);

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
