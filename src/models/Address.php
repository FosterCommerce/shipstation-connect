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
	private ?string $company = null;

	#[Groups(['export'])]
	#[SerializedName('Address1')]
	private ?string $address1 = null;

	#[Groups(['export'])]
	#[SerializedName('Address2')]
	private ?string $address2 = null;

	#[Groups(['export'])]
	#[SerializedName('City')]
	private ?string $city = null;

	#[Groups(['export'])]
	#[SerializedName('State')]
	private ?string $state = null;

	#[Groups(['export'])]
	#[SerializedName('PostalCode')]
	private ?string $postalCode = null;

	#[Groups(['export'])]
	#[SerializedName('Country')]
	private string $country;

	#[Groups(['export'])]
	#[SerializedName('Name')]
	private string $name;

	#[Groups(['export'])]
	#[SerializedName('Phone')]
	private ?string $phone = null;

	#[Groups(['export'])]
	#[SerializedName('Email')]
	private string $email;

	public function getCompany(): ?string
	{
		return $this->company;
	}

	public function setCompany(?string $company): void
	{
		$this->company = $company;
	}

	public function getAddress1(): ?string
	{
		return $this->address1;
	}

	public function setAddress1(?string $address1): void
	{
		$this->address1 = $address1;
	}

	public function getAddress2(): ?string
	{
		return $this->address2;
	}

	public function setAddress2(?string $address2): void
	{
		$this->address2 = $address2;
	}

	public function getCity(): ?string
	{
		return $this->city;
	}

	public function setCity(?string $city): void
	{
		$this->city = $city;
	}

	public function getState(): ?string
	{
		return $this->state;
	}

	public function setState(?string $state): void
	{
		$this->state = $state;
	}

	public function getPostalCode(): ?string
	{
		return $this->postalCode;
	}

	public function setPostalCode(?string $postalCode): void
	{
		$this->postalCode = $postalCode;
	}

	public function getCountry(): string
	{
		return $this->country;
	}

	public function setCountry(string $country): void
	{
		$this->country = $country;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): void
	{
		$this->name = $name;
	}

	public function getPhone(): ?string
	{
		return $this->phone;
	}

	public function setPhone(?string $phone): void
	{
		$this->phone = $phone;
	}

	public function getEmail(): string
	{
		return $this->email;
	}

	public function setEmail(string $email): void
	{
		$this->email = $email;
	}

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
