<?php

namespace fostercommerce\shipstationconnect\models;

use craft\commerce\elements\Order as CommerceOrder;
use fostercommerce\shipstationconnect\events\OrderFieldEvent;
use fostercommerce\shipstationconnect\Plugin;
use Symfony\Component\Serializer\Annotation\SerializedName;
use yii\base\InvalidConfigException;

class Order extends Base
{
	/**
	 * @var int
	 */
	public const SHORT_FIELD_LIMIT = 100;

	/**
	 * @var int
	 */
	public const LONG_FIELD_LIMIT = 1000;

	#[SerializedName('OrderID')]
	public int $orderId;

	#[SerializedName('OrderNumber')]
	public string $orderNumber;

	#[SerializedName('OrderStatus')]
	public string $orderStatus;

	#[SerializedName('OrderTotal')]
	public float $orderTotal;

	#[SerializedName('TaxAmount')]
	public float $taxAmount;

	#[SerializedName('ShippingAmount')]
	public float $shippingAmount;

	#[SerializedName('OrderDate')]
	public ?\DateTime $orderDate;

	#[SerializedName('LastModified')]
	public ?\DateTime $lastModifiedDate;

	#[SerializedName('PaymentMethod')]
	public ?string $paymentMethod; // CDATA

	#[SerializedName('ShippingMethod')]
	public string $shippingMethod;

	/**
	 * @var Item[]
	 */
	#[SerializedName('Items')]
	public array $items;

	#[SerializedName('Customer')]
	public ?Customer $customer;

	#[SerializedName('CustomField1')]
	public string $customField1;

	#[SerializedName('CustomField2')]
	public string $customField2;

	#[SerializedName('CustomField3')]
	public string $customField3;

	#[SerializedName('InternalNotes')]
	public string $internalNotes;

	#[SerializedName('CustomerNotes')]
	public string $customerNotes;

	#[SerializedName('Gift')]
	public bool $gift;

	#[SerializedName('GiftMessage')]
	public string $giftMessage;

	/**
	 * @throws InvalidConfigException
	 * @throws \JsonException
	 */
	public static function fromCommerceOrder(CommerceOrder $commerceOrder): self
	{
		$prefix = Plugin::getInstance()?->settings->orderIdPrefix ?? '';

		$items = array_map(Item::fromCommerceLineItem(...), $commerceOrder->lineItems);

		// Include a discount as a line item if there is one.
		$totalDiscount = $commerceOrder->getTotalDiscount();
		if ($totalDiscount > 0) {
			$items[] = Item::asAdjustment($totalDiscount);
		}

		$order = new self([
			'orderId' => "{$prefix}{$commerceOrder->id}",
			'orderNumber' => static::valueFromFieldEvent(OrderFieldEvent::FIELD_ORDER_NUMBER, $commerceOrder, $commerceOrder->reference),
			'orderStatus' => $commerceOrder->getOrderStatus()?->handle,
			'orderTotal' => round($commerceOrder->totalPrice, 2),
			'taxAmount' => $commerceOrder->getTotalTax(),
			'shippingAmount' => $commerceOrder->getTotalShippingCost(),
			'orderDate' => self::formatDate($commerceOrder->dateOrdered ?? $commerceOrder->dateCreated),
			'lastModifiedDate' => self::formatDate($commerceOrder->dateUpdated ?? $commerceOrder->dateCreated),
			'paymentMethod' => $commerceOrder->getPaymentSource()?->description,
			'items' => $items,
			'customer' => Customer::fromCommerceOrder($commerceOrder),
			'customField1' => substr(htmlspecialchars(static::asString(static::valueFromFieldEvent(OrderFieldEvent::FIELD_CUSTOM_FIELD_1, $commerceOrder))), 0, self::SHORT_FIELD_LIMIT),
			'customField2' => substr(htmlspecialchars(static::asString(static::valueFromFieldEvent(OrderFieldEvent::FIELD_CUSTOM_FIELD_2, $commerceOrder))), 0, self::SHORT_FIELD_LIMIT),
			'customField3' => substr(htmlspecialchars(static::asString(static::valueFromFieldEvent(OrderFieldEvent::FIELD_CUSTOM_FIELD_3, $commerceOrder))), 0, self::SHORT_FIELD_LIMIT),
			'internalNotes' => substr(htmlspecialchars(static::asString(static::valueFromFieldEvent(OrderFieldEvent::FIELD_INTERNAL_NOTES, $commerceOrder))), 0, self::LONG_FIELD_LIMIT),
			'customerNotes' => substr(htmlspecialchars(static::asString(static::valueFromFieldEvent(OrderFieldEvent::FIELD_CUSTOMER_NOTES, $commerceOrder))), 0, self::LONG_FIELD_LIMIT),
			'gift' => empty(static::valueFromFieldEvent(OrderFieldEvent::FIELD_GIFT, $commerceOrder)) ? 'true' : 'false',
			'giftMessage' => substr(htmlspecialchars(static::asString(static::valueFromFieldEvent(OrderFieldEvent::FIELD_GIFT_MESSAGE, $commerceOrder))), 0, self::LONG_FIELD_LIMIT),
		]);

		$order->validate();

		return $order;
	}

	private static function formatDate(?\DateTime $date): ?string
	{
		if ($date === null) {
			return null;
		}

		return date_format($date, 'n/j/Y H:m');
	}
}
