<?php

namespace fostercommerce\shipstationconnect\models;

use craft\commerce\elements\Order as CommerceOrder;
use craft\elements\Address as CraftAddress;
use craft\elements\User;
use fostercommerce\shipstationconnect\Plugin;
use JMS\Serializer\Annotation\Accessor;
use JMS\Serializer\Annotation\AccessType;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;

#[AccessType([
	'type' => 'public_method',
])]
class Customer extends Base
{
	#[Groups(['export'])]
	#[SerializedName('CustomerCode')]
	#[Accessor([
		'getter' => 'getCustomerCode',
		'setter' => 'setCustomerCode',
	])]
	private string $customerCode;

	#[Groups(['export'])]
	#[SerializedName('BillTo')]
	#[Accessor([
		'getter' => 'getBillToAddress',
		'setter' => 'setBillToAddress',
	])]
	private ?Address $billToAddress = null;

	#[Groups(['export'])]
	#[SerializedName('ShipTo')]
	#[Accessor([
		'getter' => 'getShipToAddress',
		'setter' => 'setShipToAddress',
	])]
	private ?Address $shipToAddress = null;

	public function getCustomerCode(): string
	{
		return $this->limitString($this->customerCode, static::STRING_50);
	}

	public function setCustomerCode(string $customerCode): void
	{
		$this->customerCode = $customerCode;
	}

	public function getBillToAddress(): ?Address
	{
		return $this->billToAddress;
	}

	public function setBillToAddress(?Address $billToAddress): void
	{
		$this->billToAddress = $billToAddress;
	}

	public function getShipToAddress(): ?Address
	{
		return $this->shipToAddress;
	}

	public function setShipToAddress(?Address $shipToAddress): void
	{
		$this->shipToAddress = $shipToAddress;
	}

	/**
	 * @return array<int, array<int, string>>
	 */
	public function rules(): array
	{
		$rules = [
			['shipToAddress', 'required'],
			['shipToAddress', 'validateAddress'],
		];

		$billingSameAsShipping = Plugin::getInstance()?->settings->billingSameAsShipping ?? false;
		if (! $billingSameAsShipping) {
			$rules[] = ['billToAddress', 'required'];
			$rules[] = ['billToAddress', 'validateAddress'];
		}

		return $rules;
	}

	public function validateAddress(string $addressAttribute): void
	{
		if ($this->{$addressAttribute} instanceof Address && ! $this->{$addressAttribute}->validate()) {
			foreach ($this->{$addressAttribute}->getErrors() as $attribute => $error) {
				$this->addError("{$addressAttribute}.{$attribute}", $error);
			}
		}
	}

	public static function fromCommerceOrder(CommerceOrder $commerceOrder): ?self
	{
		$customer = $commerceOrder->getCustomer();

		$shippingAddress = $commerceOrder->getShippingAddress();
		$billingAddress = $commerceOrder->getBillingAddress();
		$billingSameAsShipping = Plugin::getInstance()?->settings->billingSameAsShipping ?? false;

		if ($billingSameAsShipping && ! $billingAddress instanceof CraftAddress) {
			$billingAddress = $shippingAddress;
		}

		return $customer instanceof User
			? new self([
				'customerCode' => $customer->id,
				'billToAddress' => Address::fromCommerceAddress($commerceOrder, $billingAddress, true),
				'shipToAddress' => Address::fromCommerceAddress($commerceOrder, $shippingAddress, false),
			])
			: null;
	}
}
