<?php

namespace fostercommerce\shipstationconnect\services;

use craft\base\Model as BaseModel;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin as CommercePlugin;
use craft\elements\Address;
use craft\elements\User;
use craft\helpers\UrlHelper;
use fostercommerce\shipstationconnect\events\OrderFieldEvent;
use fostercommerce\shipstationconnect\Plugin;
use SimpleXMLElement;
use yii\base\Component;
use yii\base\Event;
use yii\base\InvalidConfigException;

class Xml extends Component
{
	public const LINEITEM_OPTION_LIMIT = 10;

	public const ORDER_FIELD_EVENT = 'orderFieldEvent';

	/**
	 * @throws InvalidConfigException
	 */
	public function shouldInclude(Order $order): bool
	{
		$billingSameAsShipping = Plugin::getInstance()?->settings->billingSameAsShipping ?? false;
		return $order->getShippingAddress()
			&& $order->getOrderStatus()
			&& ($billingSameAsShipping || $order->getBillingAddress())
			&& $order->getCustomer();
	}

	/**
	 * Build an XML document given an array of orders
	 *
	 * @param SimpleXMLElement $xml the xml to add a child to or modify
	 * @param Order[] $orders
	 * @param string $name the name of the child node, default 'Orders'
	 * @throws InvalidConfigException
	 */
	public function orders(SimpleXMLElement $xml, array $orders, string $name = 'Orders'): SimpleXMLElement
	{
		$ordersXml = $xml->getName() === $name ? $xml : $xml->addChild($name);

		if ($ordersXml === null) {
			throw new \RuntimeException("Unable to create child {$name}");
		}

		foreach ($orders as $order) {
			if ($this->shouldInclude($order)) {
				$this->order($ordersXml, $order);
			}
		}

		return $xml;
	}

	/**
	 * Build an XML document given a Order instance
	 *
	 * @param SimpleXMLElement $xml the xml to add a child to or modify
	 * @param string $name the name of the child node, default 'Order'
	 * @throws InvalidConfigException
	 */
	public function order(SimpleXMLElement $xml, Order $order, string $name = 'Order'): SimpleXMLElement
	{
		$orderXml = $xml->getName() === $name ? $xml : $xml->addChild($name);

		if ($orderXml === null) {
			throw new \RuntimeException("Unable to create child {$name}");
		}

		$order_mapping = [
			'OrderID' => [
				'callback' => function ($order) {
					$prefix = Plugin::getInstance()?->settings->orderIdPrefix ?? '';
					return $prefix . $order->id;
				},
				'cdata' => false,
			],
			'OrderNumber' => [
				'callback' => function ($order) {
					$orderFieldEvent = new OrderFieldEvent([
						'field' => OrderFieldEvent::FIELD_ORDER_NUMBER,
						'order' => $order,
						'value' => $order->reference,
					]);

					Event::trigger(static::class, self::ORDER_FIELD_EVENT, $orderFieldEvent);
					return $orderFieldEvent->value;
				},
				'cdata' => false,
			],
			'OrderStatus' => [
				'callback' => function ($order) {
					return $order->getOrderStatus()->handle;
				},
				'cdata' => false,
			],
			'OrderTotal' => [
				'callback' => function ($order) {
					return round($order->totalPrice, 2);
				},
				'cdata' => false,
			],
			'TaxAmount' => [
				'callback' => function ($order) {
					return $order->getTotalTax();
				},
				'cdata' => false,
			],
			'ShippingAmount' => [
				'callback' => function ($order) {
					return $order->getTotalShippingCost();
				},
				'cdata' => false,
			],
		];
		$this->mapCraftModel($orderXml, $order_mapping, $order);

		/** @var \DateTime $orderDate */
		$orderDate = $order->dateOrdered ?? $order->dateCreated;
		$orderXml->addChild('OrderDate', date_format($orderDate, 'n/j/Y H:m'));

		/** @var \DateTime $lastModifiedDate */
		$lastModifiedDate = $order->dateUpdated ?? $order->dateCreated;
		$orderXml->addChild('LastModified', date_format($lastModifiedDate, 'n/j/Y H:m'));

		$this->shippingMethod($orderXml, $order);

		$paymentSource = $order->getPaymentSource();
		if ($paymentSource) {
			$this->addChildWithCDATA($orderXml, 'PaymentMethod', $paymentSource->description);
		}

		$items_xml = $this->items($orderXml, $order->getLineItems());
		$this->discount($items_xml, $order);

		$customer = $order->getCustomer();

		if ($customer !== null) {
			$customerXml = $this->customer($orderXml, $customer);
			$this->billTo($customerXml, $order, $customer);
			$this->shipTo($customerXml, $order, $customer);
		}

		$this->customOrderFields($orderXml, $order);

		return $orderXml;
	}

	/**
	 * Add a child with the shipping method to the order_xml, allowing plugins to override as needed
	 *
	 * @param SimpleXMLElement $orderXml the order xml to add a child to
	 */
	public function shippingMethod(SimpleXMLElement $orderXml, Order $order): void
	{
		$orderFieldEvent = new OrderFieldEvent([
			'field' => OrderFieldEvent::FIELD_SHIPPING_METHOD,
			'order' => $order,
		]);
		Event::trigger(static::class, self::ORDER_FIELD_EVENT, $orderFieldEvent);

		if (! $orderFieldEvent->value) {
			$orderFieldEvent->value = $order->shippingMethodHandle;
		}

		$this->addChildWithCDATA($orderXml, 'ShippingMethod', $this->asString($orderFieldEvent->value));
	}

	/**
	 * Build an XML document given an array of items
	 *
	 * @param SimpleXMLElement $xml the xml to add a child to or modify
	 * @param LineItem[] $items
	 * @param string $name the name of the child node, default 'Items'
	 */
	public function items(SimpleXMLElement $xml, array $items, string $name = 'Items'): SimpleXMLElement
	{
		$itemsXml = $xml->getName() === $name ? $xml : $xml->addChild($name);

		if ($itemsXml === null) {
			throw new \RuntimeException("Unable to create child {$name}");
		}

		foreach ($items as $item) {
			$this->item($itemsXml, $item);
		}

		return $itemsXml;
	}

	/**
	 * Build an XML document given a LineItem instance
	 *
	 * @param SimpleXMLElement $xml the xml to add a child to or modify
	 * @param string $name the name of the child node, default 'Item'
	 */
	public function item(SimpleXMLElement $xml, LineItem $item, string $name = 'Item'): SimpleXMLElement
	{
		$itemXml = $xml->getName() === $name ? $xml : $xml->addChild($name);

		if ($itemXml === null) {
			throw new \RuntimeException("Unable to create child {$name}");
		}

		$item_mapping = [
			'SKU' => [
				'callback' => function ($item) {
					return $item->snapshot['sku'];
				},
			],
			'Name' => [
				'callback' => function ($item) {
					return substr($item->description, 0, 200);
				},
			],
			'Weight' => [
				'callback' => function ($item) {
					$weight_units = CommercePlugin::getInstance()?->settings->weightUnits;

					if ($weight_units === 'kg') {
						// kilograms need to be converted to grams for ShipStation
						return round($item->weight * 1000, 2);
					}

					return round($item->weight, 2);
				},
				'cdata' => false,
			],
			'Quantity' => [
				'field' => 'qty',
				'cdata' => false,
			],
			'UnitPrice' => [
				'callback' => function ($item) {
					return round($item->salePrice, 2);
				},
				'cdata' => false,
			],
			'ImageUrl' => [
				'callback' => function ($item) {
					$productImagesHandle = Plugin::getInstance()?->settings->productImagesHandle;
					$purchasable = $item->getPurchasable();
					if ($productImagesHandle !== null && $purchasable !== null) {
						$assetQuery = $purchasable->{$productImagesHandle};
						if ($assetQuery === null) {
							// Fallback to the product if the variant does not have an asset
							$assetQuery = $purchasable->product->{$productImagesHandle};
						}
						if ($assetQuery !== null) {
							$asset = $assetQuery->one();
							if ($asset !== null) {
								return UrlHelper::siteUrl($asset->getUrl());
							}
						}
					}

					return null;
				},
				'cdata' => false,
			],
		];
		$this->mapCraftModel($itemXml, $item_mapping, $item);

		$weightUnits = match (CommercePlugin::getInstance()?->settings->weightUnits) {
			'lb' => 'Pounds',
			default => 'Grams',
		};

		$itemXml->addChild('WeightUnits', $weightUnits);

		if (isset($item->options)) {
			$this->options($itemXml, $item->options);
		}

		return $itemXml;
	}

	/**
	 * Discounts (a.k.a. coupons) are added as items
	 */
	public function discount(SimpleXMLElement $xml, Order $order, string $name = 'Item'): null|SimpleXMLElement
	{
		// If no discount was applied, skip this
		if ($order->getTotalDiscount() >= 0) {
			return null;
		}

		$discountXml = $xml->getName() === $name ? $xml : $xml->addChild($name);

		if ($discountXml === null) {
			throw new \RuntimeException("Unable to create child {$name}");
		}

		$discount_mapping = [
			'SKU' => [
				'callback' => function ($order) {
					return '';
				},
				'cdata' => false,
			],
			'Name' => 'couponCode',
			'Quantity' => [
				'callback' => function ($order) {
					return 1;
				},
				'cdata' => false,
			],
			'UnitPrice' => [
				'callback' => function ($order) {
					return round($order->getTotalDiscount(), 2);
				},
				'cdata' => false,
			],
			'Adjustment' => [
				'callback' => function ($order) {
					return 'true';
				},
				'cdata' => false,
			],
		];

		$this->mapCraftModel($discountXml, $discount_mapping, $order);

		return $discountXml;
	}

	/**
	 * Build an XML document given a hash of options
	 *
	 * @param SimpleXMLElement $xml the xml to add a child to or modify
	 * @param array<string, mixed> $options
	 * @param string $name the name of the child node, default 'Options'
	 * @throws \JsonException
	 */
	public function options(SimpleXMLElement $xml, array $options, string $name = 'Options'): SimpleXMLElement
	{
		$optionsXml = $xml->getName() === $name ? $xml : $xml->addChild($name);

		if ($optionsXml === null) {
			throw new \RuntimeException("Unable to create child {$name}");
		}

		$index = 0;
		foreach ($options as $key => $value) {
			$optionXml = $optionsXml->addChild('Option');

			if ($optionXml === null) {
				throw new \RuntimeException("Unable to create option child {$name}");
			}

			$optionXml->addChild('Name', $key);

			$this->addChildWithCDATA($optionXml, 'Value', substr(htmlspecialchars($this->asString($value)), 0, 100));

			// ShipStation limits the number of options on any line item
			$index++;
			if ($index === self::LINEITEM_OPTION_LIMIT) {
				break;
			}
		}

		return $xml;
	}

	/**
	 * Build an XML document given a Customer instance
	 *
	 * @param SimpleXMLElement $xml the xml to add a child to or modify
	 * @param string $name the name of the child node, default 'Customer'
	 */
	public function customer(SimpleXMLElement $xml, User $customer, string $name = 'Customer'): SimpleXMLElement
	{
		$customerXml = $xml->getName() === $name ? $xml : $xml->addChild($name);

		if ($customerXml === null) {
			throw new \RuntimeException("Unable to create customer child {$name}");
		}

		$customerMapping = [
			'CustomerCode' => 'id',
		];
		$this->mapCraftModel($customerXml, $customerMapping, $customer);

		return $customerXml;
	}

	/**
	 * Add a BillTo address XML Child
	 *
	 * @param SimpleXMLElement $customerXml the xml to add a child to or modify
	 */
	public function billTo(SimpleXMLElement $customerXml, Order $order, User $customer): void
	{
		$billingAddress = $order->getBillingAddress();
		if (! $billingAddress) {
			$billingSameAsShipping = Plugin::getInstance()->settings->billingSameAsShipping ?? false;
			if ($billingSameAsShipping) {
				$billingAddress = $order->getShippingAddress();
			}
		}

		if ($billingAddress) {
			$billToXml = $this->address($customerXml, $billingAddress, 'BillTo');
			$name = $this->generateName($billingAddress->firstName, $billingAddress->lastName);

			if ($name === null) {
				$name = $this->generateName($customer->firstName, $customer->lastName) ?: 'Unknown';
			}
			$this->addChildWithCDATA($billToXml, 'Name', $name);
			$billToXml->addChild('Email', $order->email);
		}
	}

	/**
	 * Add a ShipTo address XML Child
	 *
	 * @param SimpleXMLElement $customer_xml the xml to add a child to or modify
	 * @throws \JsonException
	 */
	public function shipTo(SimpleXMLElement $customer_xml, Order $order, User $customer): void
	{
		$shippingAddress = $order->getShippingAddress();
		if ($shippingAddress === null) {
			return;
		}

		$shipToXml = $this->address($customer_xml, $shippingAddress, 'ShipTo');
		$name = $this->generateName($shippingAddress->firstName, $shippingAddress->lastName);

		if ($name !== null) {
			$name = $this->generateName($customer->firstName, $customer->lastName) ?: 'Unknown';
		}
		$this->addChildWithCDATA($shipToXml, 'Name', $this->asString($name));
	}

	/**
	 * Build an XML document given a Address instance
	 *
	 * @param SimpleXMLElement $xml the xml to add a child to or modify
	 * @param string $name the name of the child node, default 'Address'
	 */
	public function address(SimpleXMLElement $xml, ?Address $address = null, string $name = 'Address'): SimpleXMLElement
	{
		$addressXml = $xml->getName() === $name ? $xml : $xml->addChild($name);

		if ($addressXml === null) {
			throw new \RuntimeException("Unable to create address child {$name}");
		}

		$phoneNumberFieldHandle = Plugin::getInstance()?->settings->phoneNumberFieldHandle ?? '';
		if ($address !== null) {
			$address_mapping = [
				'Company' => 'organization',
				'Address1' => 'addressLine1',
				'Address2' => 'addressLine2',
				'City' => 'locality',
				'State' => 'administrativeArea',
				'PostalCode' => 'postalCode',
				/*'Country' =>  [
					'callback' => function ($address) {
						return $address->countryId ? $address->getCountry()->iso : null;
					},
					'cdata' => false,
				]
				*/
				'Country' => 'countryCode',
			];

			if ($phoneNumberFieldHandle !== '') {
				$address_mapping['Phone'] = $phoneNumberFieldHandle;
			}

			$this->mapCraftModel($addressXml, $address_mapping, $address);
		}

		return $addressXml;
	}

	/**
	 * Allow plugins to add custom fields to the order
	 *
	 * @param SimpleXMLElement $orderXml the order xml to add a child
	 * @throws \JsonException
	 */
	public function customOrderFields(SimpleXMLElement $orderXml, Order $order): SimpleXMLElement
	{
		$customFields = [
			[OrderFieldEvent::FIELD_CUSTOM_FIELD_1, 100],
			[OrderFieldEvent::FIELD_CUSTOM_FIELD_2, 100],
			[OrderFieldEvent::FIELD_CUSTOM_FIELD_3, 100],
			[OrderFieldEvent::FIELD_INTERNAL_NOTES, 1000],
			[OrderFieldEvent::FIELD_CUSTOMER_NOTES, 1000],
			[OrderFieldEvent::FIELD_GIFT, 100],
			[OrderFieldEvent::FIELD_GIFT_MESSAGE, 1000],
		];

		foreach ($customFields as [$fieldName, $charLimit]) {
			$orderFieldEvent = new OrderFieldEvent([
				'field' => $fieldName,
				'order' => $order,
			]);

			Event::trigger(static::class, self::ORDER_FIELD_EVENT, $orderFieldEvent);
			$data = $orderFieldEvent->value ?: '';

			// Gift field requires a boolean value
			if ($fieldName === OrderFieldEvent::FIELD_GIFT) {
				$data = $data ? 'true' : 'false';
				$orderXml->addChild($fieldName, $data);
			} else {
				if ($orderFieldEvent->cdata) {
					$this->addChildWithCDATA($orderXml, $fieldName, substr(htmlspecialchars($this->asString($data)), 0, $charLimit));
				} else {
					$orderXml->addChild($fieldName, substr(htmlspecialchars($this->asString($data)), 0, $charLimit));
				}
			}
		}

		return $orderXml;
	}

	/**
	 * @param array<string, mixed> $mapping
	 */
	protected function mapCraftModel(SimpleXMLElement $xml, array $mapping, BaseModel $model): SimpleXMLElement
	{
		foreach ($mapping as $name => $attr) {
			$value = $this->valueFromMappingAndModel($attr, $model);

			//wrap in cdata unless explicitly set not to
			if (! is_array($attr) || ! array_key_exists('cdata', $attr) || $attr['cdata']) {
				$this->addChildWithCDATA($xml, $name, $this->asString($value));
			} else {
				$xml->addChild($name, $value);
			}
		}
		return $xml;
	}

	/**
	 * Retrieve data from a Craft model by field name or method name.
	 *
	 * Example usage:
	 *   by field:
	 *   $value = $this->valueFromMappingAndModel('id', $order);
	 *   echo $value; // order id, wrapped in CDATA tag
	 *
	 *   by field with custom options
	 *   $options = ['field' => 'totalAmount', 'cdata' => false];
	 *   $value = $this->valueFromMappingAndModel($options, $value);
	 *   echo $value; // the order's totalAmount, NOT wrapped in cdata
	 *
	 *   by annonymous function (closure):
	 *   $value = $this->valueFromMappingAndModel(function($order) {
	 *      return is_null($order->name) ? 'N/A' : $order->name;
	 *   }, $order);
	 *   echo $value; // the order's name if it is set, or 'N/A' otherwise
	 *
	 *   @param mixed $options, a string field name or
	 *                          a callback accepting the model instance as its only parameter or
	 *                          a hash containing options with a 'field' or 'callback' key
	 *   @param BaseModel $model, an instance of a craft model
	 */
	protected function valueFromMappingAndModel(mixed $options, BaseModel $model): ?string
	{
		$value = null;

		//if field name exists in the options array
		if (is_array($options) && array_key_exists('field', $options)) {
			$field = $options['field'];
			$value = $model->{$field};
		}
		//if value is coming from a callback in the options array
		elseif (is_array($options) && array_key_exists('callback', $options)) {
			$callback = $options['callback'];
			$value = $callback($model);
		}
		//if value is a callback
		elseif (is_object($options) && is_callable($options)) {
			$value = $options($model);
		}
		//if value is an attribute on the model, passed as a string field name
		elseif (is_string($options)) {
			$value = $model->{$options};
		}
		// if null, leave blank
		elseif ($options === null) {
			$value = '';
		}

		if ($value === true || $value === false) {
			$value = $value ? 'true' : 'false';
		}
		return $value;
	}

	/**
	 * Add a child with <!CDATA[...]]>
	 *
	 * We cannot simply do this by manipulating the string, because SimpleXMLElement and/or Craft will encode it
	 *
	 * @param SimpleXMLElement $xml the parent to which we're adding a child
	 * @param string $name the xml node name
	 * @param string $value the value of the new child node, which will be wrapped in CDATA
	 * @return SimpleXMLElement, the new child
	 */
	protected function addChildWithCDATA(SimpleXMLElement $xml, string $name, string $value): SimpleXMLElement
	{
		$newChild = $xml->addChild($name);

		if ($newChild === null) {
			throw new \RuntimeException("Unable to create new child {$name}");
		}

		$node = dom_import_simplexml($newChild);
		$section = $node->ownerDocument?->createCDATASection($value);
		if ($section !== null) {
			$node->appendChild($section);
		}

		return $newChild;
	}

	/**
	 * @throws \JsonException
	 */
	private function asString(mixed $value): string
	{
		if ($value instanceof \Stringable) {
			return (string) $value;
		}

		return json_encode($value, JSON_THROW_ON_ERROR);
	}

	private function generateName(?string $firstName, ?string $lastName): ?string
	{
		if ($firstName === null && $lastName === null) {
			return null;
		}

		$names = [$firstName, $lastName];
		$names = array_filter($names, static fn ($name) => $name !== null && $name !== '');

		return implode(' ', $names);
	}
}
