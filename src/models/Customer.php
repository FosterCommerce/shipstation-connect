<?php

namespace fostercommerce\shipstationconnect\models;

use craft\commerce\elements\Order as CommerceOrder;
use fostercommerce\shipstationconnect\Plugin;
use Symfony\Component\Serializer\Annotation\SerializedName;

class Customer extends Base
{
	#[SerializedName('CustomerCode')]
	public string $customerCode;

	#[SerializedName('BillTo')]
	public Address $billToAddress;

	#[SerializedName('ShipTo')]
	public Address $shipToAddress;

	public static function fromCommerceOrder(CommerceOrder $commerceOrder): ?self
	{
		$customer = $commerceOrder->getCustomer();

		$shippingAddress = $commerceOrder->getShippingAddress();
		$billingAddress = $commerceOrder->getBillingAddress();
		$billingSameAsShipping = Plugin::getInstance()?->settings->billingSameAsShipping ?? false;

		if ($billingSameAsShipping && $billingAddress === null) {
			$billingAddress = $shippingAddress;
		}

		return $customer
			? new self([
				'customerCode' => $customer->id,
				'billToAddress' => Address::fromCommerceAddress($commerceOrder, $billingAddress),
				'shipToAddress' => Address::fromCommerceAddress($commerceOrder, $shippingAddress),
			])
			: null;
	}
}
