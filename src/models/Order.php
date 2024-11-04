<?php

namespace fostercommerce\shipstationconnect\models;

use craft\commerce\elements\Order as CommerceOrder;
use fostercommerce\shipstationconnect\Plugin;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Ignore;
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
	public ?\DateTime $orderDate = null;

	#[SerializedName('LastModified')]
	public ?\DateTime $lastModifiedDate = null;

	#[SerializedName('PaymentMethod')]
	public ?string $paymentMethod = null;

	#[SerializedName('ShippingMethod')]
	public string $shippingMethod;

	/**
	 * @var Item[]
	 */
	#[SerializedName('Items')]
	public array $items;

	#[SerializedName('Customer')]
	public ?Customer $customer;

	#[SerializedName('InternalNotes')]
	public string $internalNotes;

	#[SerializedName('Gift')]
	public bool $gift = false;

	#[Ignore]
	public CommerceOrder $parentOrder;

	#[SerializedName('CustomField1')]
	private string $customField1;

	#[SerializedName('CustomField2')]
	private string $customField2;

	#[SerializedName('CustomField3')]
	private string $customField3;

	#[SerializedName('CustomerNotes')]
	private string $customerNotes;

	#[SerializedName('GiftMessage')]
	private string $giftMessage;

	public function setCustomField1(string $customField1): void
	{
		$this->customField1 = $customField1;
	}

	/**
	 * @throws \JsonException
	 */
	public function getCustomField1(): string
	{
		return substr(htmlspecialchars(static::asString($this->customField1)), 0, self::SHORT_FIELD_LIMIT);
	}

	public function setCustomField2(string $customField2): void
	{
		$this->customField2 = $customField2;
	}

	/**
	 * @throws \JsonException
	 */
	public function getCustomField2(): string
	{
		return substr(htmlspecialchars(static::asString($this->customField2)), 0, self::SHORT_FIELD_LIMIT);
	}

	public function setCustomField3(string $customField3): void
	{
		$this->customField3 = $customField3;
	}

	/**
	 * @throws \JsonException
	 */
	public function getCustomField3(): string
	{
		return substr(htmlspecialchars(static::asString($this->customField3)), 0, self::SHORT_FIELD_LIMIT);
	}

	public function setInternalNotes(string $internalNotes): void
	{
		$this->internalNotes = $internalNotes;
	}

	/**
	 * @throws \JsonException
	 */
	public function getInternalNotes(): string
	{
		return substr(htmlspecialchars(static::asString($this->internalNotes)), 0, self::LONG_FIELD_LIMIT);
	}

	public function setCustomerNotes(string $customerNotes): void
	{
		$this->customerNotes = $customerNotes;
	}

	/**
	 * @throws \JsonException
	 */
	public function getCustomerNotes(): string
	{
		return substr(htmlspecialchars(static::asString($this->customerNotes)), 0, self::LONG_FIELD_LIMIT);
	}

	public function setGiftMessage(string $giftMessage): void
	{
		$this->giftMessage = $giftMessage;
	}

	/**
	 * @throws \JsonException
	 */
	public function getGiftMessage(): string
	{
		return substr(htmlspecialchars(static::asString($this->giftMessage)), 0, self::LONG_FIELD_LIMIT);
	}

	/**
	 * @throws InvalidConfigException
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
			'orderNumber' => $commerceOrder->reference,
			'orderStatus' => $commerceOrder->getOrderStatus()?->handle,
			'orderTotal' => round($commerceOrder->totalPrice, 2),
			'taxAmount' => $commerceOrder->getTotalTax(),
			'shippingAmount' => $commerceOrder->getTotalShippingCost(),
			'orderDate' => self::formatDate($commerceOrder->dateOrdered ?? $commerceOrder->dateCreated),
			'lastModifiedDate' => self::formatDate($commerceOrder->dateUpdated ?? $commerceOrder->dateCreated),
			'paymentMethod' => $commerceOrder->getPaymentSource()?->description,
			'items' => $items,
			'customer' => Customer::fromCommerceOrder($commerceOrder),
			'parentOrder' => $commerceOrder,
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
