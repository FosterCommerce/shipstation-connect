<?php

namespace fostercommerce\shipstationconnect\models;

use craft\commerce\elements\Order as CommerceOrder;
use craft\elements\Address as CraftAddress;
use fostercommerce\shipstationconnect\Plugin;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;

class Address extends Base
{
	#[Groups(['export'])]
	#[SerializedName('Company')]
	public ?string $company = null;

	#[Groups(['export'])]
	#[SerializedName('Address1')]
	public ?string $address1 = null;

	#[Groups(['export'])]
	#[SerializedName('Address2')]
	public ?string $address2 = null;

	#[Groups(['export'])]
	#[SerializedName('City')]
	public ?string $city = null;

	#[Groups(['export'])]
	#[SerializedName('State')]
	public ?string $state = null;

	#[Groups(['export'])]
	#[SerializedName('PostalCode')]
	public ?string $postalCode = null;

	#[Groups(['export'])]
	#[SerializedName('Country')]
	public string $country;

	#[Groups(['export'])]
	#[SerializedName('Name')]
	public string $name;

	#[Groups(['export'])]
	#[SerializedName('Phone')]
	public ?string $phone = null;

	#[Groups(['export'])]
	#[SerializedName('Email')]
	public string $email;

	public static function fromCommerceAddress(CommerceOrder $commerceOrder, ?CraftAddress $commerceAddress): ?self
	{
		if (! $commerceAddress instanceof CraftAddress) {
			return null;
		}

		$phoneNumberFieldHandle = Plugin::getInstance()?->settings->phoneNumberFieldHandle;
		$phone = $commerceAddress->{$phoneNumberFieldHandle} ?? null;

		return new self([
			'name' => $commerceAddress->fullName
				?? $commerceOrder->getCustomer()?->fullName
				?? 'Unknown',
			'phone' => $phone,
			'email' => $commerceOrder->email,
			'company' => $commerceAddress->organization,
			'address1' => $commerceAddress->addressLine1,
			'address2' => $commerceAddress->addressLine2,
			'city' => $commerceAddress->locality,
			'state' => $commerceAddress->administrativeArea,
			'postalCode' => $commerceAddress->postalCode,
			'country' => $commerceAddress->countryCode,
		]);
	}
}
