<?php

namespace fostercommerce\shipstationconnect\models;

use craft\commerce\elements\Order as CommerceOrder;
use fostercommerce\shipstationconnect\Plugin;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\XmlList;
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

	#[Groups(['export'])]
	#[SerializedName('OrderID')]
	private int $orderId;

	#[Groups(['export'])]
	#[SerializedName('OrderNumber')]
	private string $orderNumber;

	#[Groups(['export'])]
	#[SerializedName('OrderStatus')]
	private ?string $orderStatus = null;

	#[Groups(['export'])]
	#[SerializedName('OrderTotal')]
	private float $orderTotal;

	#[Groups(['export'])]
	#[SerializedName('TaxAmount')]
	private float $taxAmount;

	#[Groups(['export'])]
	#[SerializedName('ShippingAmount')]
	private float $shippingAmount;

	#[Groups(['export'])]
	#[SerializedName('LastModified')]
	#[Type("DateTime<'n/j/Y H:m'>")]
	private ?\DateTime $lastModifiedDate = null;

	#[Groups(['export'])]
	#[SerializedName('PaymentMethod')]
	private ?string $paymentMethod = null;

	#[Groups(['export'])]
	#[SerializedName('ShippingMethod')]
	private ?string $shippingMethod = null;

	/**
	 * @var Item[]
	 */
	#[Groups(['export'])]
	#[SerializedName('Items')]
	#[XmlList(entry: 'Item')]
	private array $items;

	#[Groups(['export'])]
	#[SerializedName('Customer')]
	private ?Customer $customer = null;

	#[Groups(['export'])]
	#[SerializedName('InternalNotes')]
	private string $internalNotes = '';

	#[Groups(['export'])]
	#[SerializedName('Gift')]
	private bool $gift = false;

	#[Groups(['export'])]
	#[SerializedName('OrderDate')]
	#[Type("DateTime<'n/j/Y H:m'>")]
	private ?\DateTime $orderDate = null;

	#[Exclude]
	private CommerceOrder $parent;

	#[Groups(['export'])]
	#[SerializedName('CustomField1')]
	private string $customField1 = '';

	#[Groups(['export'])]
	#[SerializedName('CustomField2')]
	private string $customField2 = '';

	#[Groups(['export'])]
	#[SerializedName('CustomField3')]
	private string $customField3 = '';

	#[Groups(['export'])]
	#[SerializedName('CustomerNotes')]
	private string $customerNotes = '';

	#[Groups(['export'])]
	#[SerializedName('GiftMessage')]
	private string $giftMessage = '';

	public function getOrderId(): int
	{
		return $this->orderId;
	}

	public function setOrderId(int $orderId): void
	{
		$this->orderId = $orderId;
	}

	public function getOrderNumber(): string
	{
		return $this->orderNumber;
	}

	public function setOrderNumber(string $orderNumber): void
	{
		$this->orderNumber = $orderNumber;
	}

	public function getOrderStatus(): ?string
	{
		return $this->orderStatus;
	}

	public function setOrderStatus(?string $orderStatus): void
	{
		$this->orderStatus = $orderStatus;
	}

	public function getOrderTotal(): float
	{
		return $this->orderTotal;
	}

	public function setOrderTotal(float $orderTotal): void
	{
		$this->orderTotal = $orderTotal;
	}

	public function getTaxAmount(): float
	{
		return $this->taxAmount;
	}

	public function setTaxAmount(float $taxAmount): void
	{
		$this->taxAmount = $taxAmount;
	}

	public function getShippingAmount(): float
	{
		return $this->shippingAmount;
	}

	public function setShippingAmount(float $shippingAmount): void
	{
		$this->shippingAmount = $shippingAmount;
	}

	public function getLastModifiedDate(): ?\DateTime
	{
		return $this->lastModifiedDate;
	}

	public function setLastModifiedDate(?\DateTime $lastModifiedDate): void
	{
		$this->lastModifiedDate = $lastModifiedDate;
	}

	public function getPaymentMethod(): ?string
	{
		return $this->paymentMethod;
	}

	public function setPaymentMethod(?string $paymentMethod): void
	{
		$this->paymentMethod = $paymentMethod;
	}

	public function getShippingMethod(): ?string
	{
		return $this->shippingMethod;
	}

	public function setShippingMethod(?string $shippingMethod): void
	{
		$this->shippingMethod = $shippingMethod;
	}

	/**
	 * @return Item[]
	 */
	public function getItems(): array
	{
		return $this->items;
	}

	/**
	 * @param Item[] $items
	 */
	public function setItems(array $items): void
	{
		$this->items = $items;
	}

	public function getCustomer(): ?Customer
	{
		return $this->customer;
	}

	public function setCustomer(?Customer $customer): void
	{
		$this->customer = $customer;
	}

	/**
	 * @throws \JsonException
	 */
	public function getInternalNotes(): string
	{
		return substr(htmlspecialchars(static::asString($this->internalNotes)), 0, self::LONG_FIELD_LIMIT);
	}

	public function setInternalNotes(string $internalNotes): void
	{
		$this->internalNotes = $internalNotes;
	}

	public function isGift(): bool
	{
		return $this->gift;
	}

	public function setGift(bool $gift): void
	{
		$this->gift = $gift;
	}

	public function getOrderDate(): ?\DateTime
	{
		return $this->orderDate;
	}

	public function setOrderDate(?\DateTime $orderDate): void
	{
		$this->orderDate = $orderDate;
	}

	public function getParent(): CommerceOrder
	{
		return $this->parent;
	}

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
	 * @return array<int, array<int, string>>
	 */
	public function rules(): array
	{
		return [
			['customer', 'required'],
			['customer', 'validateCustomer'],
			['orderStatus', 'required'],
		];
	}

	public function validateCustomer(string $customerAttribute): void
	{
		if ($this->customer instanceof Customer && ! $this->customer->validate()) {
			foreach ($this->customer->getErrors() as $attribute => $error) {
				$this->addError("{$customerAttribute}.{$attribute}", $error);
			}
		}
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
		if ($totalDiscount !== 0.0) {
			$items[] = Item::asAdjustment('couponCode', $totalDiscount);
		}

		return new self([
			'orderId' => "{$prefix}{$commerceOrder->id}",
			'orderNumber' => $commerceOrder->reference,
			'orderStatus' => $commerceOrder->getOrderStatus()?->handle,
			'orderTotal' => round($commerceOrder->totalPrice, 2),
			'taxAmount' => $commerceOrder->getTotalTax(),
			'shippingAmount' => $commerceOrder->getTotalShippingCost(),
			'orderDate' => $commerceOrder->dateOrdered ?? $commerceOrder->dateCreated,
			'lastModifiedDate' => $commerceOrder->dateUpdated ?? $commerceOrder->dateCreated,
			'paymentMethod' => $commerceOrder->getPaymentSource()?->description,
			'shippingMethod' => $commerceOrder->shippingMethodHandle,
			'items' => $items,
			'customer' => Customer::fromCommerceOrder($commerceOrder),
			'parent' => $commerceOrder,
		]);
	}

	protected function setParent(CommerceOrder $parent): void
	{
		$this->parent = $parent;
	}
}
