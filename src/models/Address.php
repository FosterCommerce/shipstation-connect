<?php

namespace fostercommerce\shipstationconnect\models;

use craft\commerce\elements\Order as CommerceOrder;
use craft\elements\Address as CommerceAddress;
use fostercommerce\shipstationconnect\Plugin;
use Symfony\Component\Serializer\Annotation\SerializedName;

class Address extends Base
{
	#[SerializedName('Company')]
	public ?string $company;

	#[SerializedName('Address1')]
	public ?string $address1;

	#[SerializedName('Address2')]
	public ?string $address2;

	#[SerializedName('City')]
	public ?string $city;

	#[SerializedName('State')]
	public ?string $state;

	#[SerializedName('PostalCode')]
	public ?string $postalCode;

	#[SerializedName('Country')]
	public string $country;

	#[SerializedName('Name')]
	public string $name;

	#[SerializedName('Phone')]
	public ?string $phone;

	#[SerializedName('Email')]
	public string $email;

	public static function fromCommerceAddress(CommerceOrder $commerceOrder, ?CommerceAddress $commerceAddress): ?self
	{
		if ($commerceAddress === null) {
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
