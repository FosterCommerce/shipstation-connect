<?php

namespace fostercommerce\shipstationconnect\models;

use craft\commerce\elements\Order as CommerceOrder;
use craft\elements\Address as CraftAddress;
use craft\elements\User;
use fostercommerce\shipstationconnect\Plugin;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;

class Customer extends Base
{
	#[Groups(['export'])]
	#[SerializedName('CustomerCode')]
	private string $customerCode;

	#[Groups(['export'])]
	#[SerializedName('BillTo')]
	private ?Address $billToAddress = null;

	#[Groups(['export'])]
	#[SerializedName('ShipTo')]
	private ?Address $shipToAddress = null;

	public function getCustomerCode(): string
	{
		return $this->customerCode;
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
		];

		$billingSameAsShipping = Plugin::getInstance()?->settings->billingSameAsShipping ?? false;
		if (! $billingSameAsShipping) {
			$rules[] = ['billToAddress', 'required'];
		}

		return $rules;
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
				'billToAddress' => Address::fromCommerceAddress($commerceOrder, $billingAddress),
				'shipToAddress' => Address::fromCommerceAddress($commerceOrder, $shippingAddress),
			])
			: null;
	}
}
