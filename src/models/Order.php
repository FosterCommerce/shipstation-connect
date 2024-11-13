<?php

namespace fostercommerce\shipstationconnect\models;

use craft\commerce\elements\Order as CommerceOrder;
use fostercommerce\shipstationconnect\Plugin;
use JMS\Serializer\Annotation\Accessor;
use JMS\Serializer\Annotation\AccessType;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\XmlList;
use yii\base\InvalidConfigException;

#[AccessType([
	'type' => 'public_method',
])]
class Order extends Base
{
	#[Groups(['export'])]
	#[SerializedName('OrderID')]
	#[Accessor([
		'getter' => 'getOrderId',
		'setter' => 'setOrderId',
	])]
	private int $orderId;

	#[Groups(['export'])]
	#[SerializedName('OrderNumber')]
	#[Accessor([
		'getter' => 'getOrderNumber',
		'setter' => 'setOrderNumber',
	])]
	private string $orderNumber;

	#[Groups(['export'])]
	#[SerializedName('OrderStatus')]
	#[Accessor([
		'getter' => 'getOrderStatus',
		'setter' => 'setOrderStatus',
	])]
	private ?string $orderStatus = null;

	#[Groups(['export'])]
	#[SerializedName('OrderTotal')]
	#[Accessor([
		'getter' => 'getOrderTotal',
		'setter' => 'setOrderTotal',
	])]
	private float $orderTotal;

	#[Groups(['export'])]
	#[SerializedName('TaxAmount')]
	#[Accessor([
		'getter' => 'getTaxAmount',
		'setter' => 'setTaxAmount',
	])]
	private float $taxAmount;

	#[Groups(['export'])]
	#[SerializedName('ShippingAmount')]
	#[Accessor([
		'getter' => 'getShippingAmount',
		'setter' => 'setShippingAmount',
	])]
	private float $shippingAmount;

	#[Groups(['export'])]
	#[SerializedName('LastModified')]
	#[Type("DateTime<'n/j/Y H:i'>")]
	#[Accessor([
		'getter' => 'getLastModifiedDate',
		'setter' => 'setLastModifiedDate',
	])]
	private ?\DateTime $lastModifiedDate = null;

	#[Groups(['export'])]
	#[SerializedName('PaymentMethod')]
	#[Accessor([
		'getter' => 'getPaymentMethod',
		'setter' => 'setPaymentMethod',
	])]
	private ?string $paymentMethod = null;

	#[Groups(['export'])]
	#[SerializedName('ShippingMethod')]
	#[Accessor([
		'getter' => 'getShippingMethod',
		'setter' => 'setShippingMethod',
	])]
	private ?string $shippingMethod = null;

	/**
	 * @var Item[]
	 */
	#[Groups(['export'])]
	#[SerializedName('Items')]
	#[XmlList(entry: 'Item')]
	#[Accessor([
		'getter' => 'getItems',
		'setter' => 'setItems',
	])]
	private array $items;

	#[Groups(['export'])]
	#[SerializedName('Customer')]
	#[Accessor([
		'getter' => 'getCustomer',
		'setter' => 'setCustomer',
	])]
	private ?Customer $customer = null;

	#[Groups(['export'])]
	#[SerializedName('InternalNotes')]
	#[Accessor([
		'getter' => 'getInternalNotes',
		'setter' => 'setInternalNotes',
	])]
	private string $internalNotes = '';

	#[Groups(['export'])]
	#[SerializedName('Gift')]
	#[Accessor([
		'getter' => 'isGift',
		'setter' => 'setGift',
	])]
	private bool $gift = false;

	#[Groups(['export'])]
	#[SerializedName('OrderDate')]
	#[Type("DateTime<'n/j/Y H:i'>")]
	#[Accessor([
		'getter' => 'getOrderDate',
		'setter' => 'setOrderDate',
	])]
	private ?\DateTime $orderDate = null;

	#[Exclude]
	#[Accessor([
		'getter' => 'getParent',
		'setter' => 'setParent',
	])]
	private CommerceOrder $parent;

	#[Groups(['export'])]
	#[SerializedName('CustomField1')]
	#[Accessor([
		'getter' => 'getCustomField1',
		'setter' => 'setCustomField1',
	])]
	private string $customField1 = '';

	#[Groups(['export'])]
	#[SerializedName('CustomField2')]
	#[Accessor([
		'getter' => 'getCustomField2',
		'setter' => 'setCustomField2',
	])]
	private string $customField2 = '';

	#[Groups(['export'])]
	#[SerializedName('CustomField3')]
	#[Accessor([
		'getter' => 'getCustomField3',
		'setter' => 'setCustomField3',
	])]
	private string $customField3 = '';

	#[Groups(['export'])]
	#[SerializedName('CustomerNotes')]
	#[Accessor([
		'getter' => 'getCustomerNotes',
		'setter' => 'setCustomerNotes',
	])]
	private string $customerNotes = '';

	#[Groups(['export'])]
	#[SerializedName('GiftMessage')]
	#[Accessor([
		'getter' => 'getGiftMessage',
		'setter' => 'setGiftMessage',
	])]
	private string $giftMessage = '';

	public function getOrderId(): string
	{
		return $this->limitString($this->orderId, static::STRING_50);
	}

	public function setOrderId(int $orderId): void
	{
		$this->orderId = $orderId;
	}

	public function getOrderNumber(): string
	{
		return $this->limitString($this->orderNumber, static::STRING_50);
	}

	public function setOrderNumber(string $orderNumber): void
	{
		$this->orderNumber = $orderNumber;
	}

	public function getOrderStatus(): ?string
	{
		return $this->limitOptionalString($this->orderStatus, static::STRING_50);
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
		return $this->limitOptionalString($this->paymentMethod, static::STRING_50);
	}

	public function setPaymentMethod(?string $paymentMethod): void
	{
		$this->paymentMethod = $paymentMethod;
	}

	public function getShippingMethod(): ?string
	{
		return $this->limitOptionalString($this->shippingMethod, static::STRING_100);
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
		return $this->limitString($this->internalNotes, static::STRING_1000);
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
		return $this->limitString($this->customField1, static::STRING_100);
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
		return $this->limitString($this->customField2, static::STRING_100);
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
		return $this->limitString($this->customField3, static::STRING_100);
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
		return $this->limitString($this->customerNotes, static::STRING_1000);
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
		return $this->limitString($this->giftMessage, static::STRING_1000);
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
