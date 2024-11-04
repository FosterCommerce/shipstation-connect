<?php

namespace fostercommerce\shipstationconnect\services;

use craft\base\Component;
use craft\commerce\elements\Order as CommerceOrder;
use DOMDocument;
use DOMElement;
use fostercommerce\shipstationconnect\events\OrderEvent;
use fostercommerce\shipstationconnect\models\Order;
use Illuminate\Support\Collection;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
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
		$classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
		$metadataAwareNameConverter = new MetadataAwareNameConverter($classMetadataFactory);
		$serializer = new Serializer(
			[new ObjectNormalizer($classMetadataFactory, $metadataAwareNameConverter)],
			[
				'xml' => new XmlEncoder(),
			]
		);

		$context = [
			'xml_root_node_name' => 'Orders',
		];

		[$orders, $failed] = collect($commerceOrders)
			->map(Order::fromCommerceOrder(...))
			->map(static function ($order): Order {
				$orderEvent = new OrderEvent([
					'transformedOrder' => $order,
				]);
				Event::trigger(static::class, self::ORDER_EVENT, $orderEvent);
				return $orderEvent->transformedOrder;
			})
			->reduceSpread(static function (array $values, Order $order) {
				/** @var Collection<int, Order> $orders */
				/** @var Collection<int, Order> $failed */
				[$orders, $failed] = $values;

				if (! $order->validate()) {
					$failed->add($order);
				} else {
					$orders->add($order);
				}
				return [$orders, $failed];
			}, collect(), collect());

		// TODO if $failed has items, do something.

		$xmlString = $serializer->serialize($orders, 'xml', $context);

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
