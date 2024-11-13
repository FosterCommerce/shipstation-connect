<?php

namespace fostercommerce\shipstationconnect\models;

use craft\commerce\elements\Order as CommerceOrder;
use craft\elements\Address as CraftAddress;
use fostercommerce\shipstationconnect\Plugin;
use JMS\Serializer\Annotation\Accessor;
use JMS\Serializer\Annotation\AccessType;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\VirtualProperty;

#[AccessType([
	'type' => 'public_method',
])]
class Address extends Base
{
	#[Groups(['export'])]
	#[SerializedName('Company')]
	#[Accessor([
		'getter' => 'getCompany',
		'setter' => 'setCompany',
	])]
	private ?string $company = null;

	#[Groups(['export'])]
	#[SerializedName('Address1')]
	#[Accessor([
		'getter' => 'getAddress1',
		'setter' => 'setAddress1',
	])]
	private ?string $address1 = null;

	#[Groups(['export'])]
	#[SerializedName('Address2')]
	#[Accessor([
		'getter' => 'getAddress2',
		'setter' => 'setAddress2',
	])]
	private ?string $address2 = null;

	#[Groups(['export'])]
	#[SerializedName('City')]
	#[Accessor([
		'getter' => 'getCity',
		'setter' => 'setCity',
	])]
	private ?string $city = null;

	#[Groups(['export'])]
	#[SerializedName('State')]
	#[Accessor([
		'getter' => 'getState',
		'setter' => 'setState',
	])]
	private ?string $state = null;

	#[Groups(['export'])]
	#[SerializedName('PostalCode')]
	#[Accessor([
		'getter' => 'getPostalCode',
		'setter' => 'setPostalCode',
	])]
	private ?string $postalCode = null;

	#[Groups(['export'])]
	#[SerializedName('Country')]
	#[Accessor([
		'getter' => 'getCountry',
		'setter' => 'setCountry',
	])]
	private string $country;

	#[Groups(['export'])]
	#[SerializedName('Name')]
	#[Accessor([
		'getter' => 'getName',
		'setter' => 'setName',
	])]
	private string $name;

	#[Groups(['export'])]
	#[SerializedName('Phone')]
	#[Accessor([
		'getter' => 'getPhone',
		'setter' => 'setPhone',
	])]
	private ?string $phone = null;

	#[Groups(['export'])]
	#[SerializedName('Email')]
	#[Accessor([
		'getter' => 'getEmail',
		'setter' => 'setEmail',
	])]
	private ?string $email = null;

	public function getCompany(): ?string
	{
		return $this->limitOptionalString($this->company, static::STRING_100);
	}

	public function setCompany(?string $company): void
	{
		$this->company = $company;
	}

	#[VirtualProperty]
	public function getAddress1(): ?string
	{
		return $this->limitOptionalString($this->address1, static::STRING_200);
	}

	public function setAddress1(?string $address1): void
	{
		$this->address1 = $address1;
	}

	public function getAddress2(): ?string
	{
		return $this->limitOptionalString($this->address2, static::STRING_200);
	}

	public function setAddress2(?string $address2): void
	{
		$this->address2 = $address2;
	}

	public function getCity(): ?string
	{
		return $this->limitOptionalString($this->city, static::STRING_100);
	}

	public function setCity(?string $city): void
	{
		$this->city = $city;
	}

	public function getState(): ?string
	{
		return $this->limitOptionalString($this->state, static::STRING_100);
	}

	public function setState(?string $state): void
	{
		$this->state = $state;
	}

	public function getPostalCode(): ?string
	{
		return $this->limitOptionalString($this->postalCode, static::STRING_50);
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
		return $this->limitString($this->name, static::STRING_100);
	}

	public function setName(string $name): void
	{
		$this->name = $name;
	}

	public function getPhone(): ?string
	{
		return $this->limitOptionalString($this->phone, static::STRING_50);
	}

	public function setPhone(?string $phone): void
	{
		$this->phone = $phone;
	}

	public function getEmail(): ?string
	{
		return $this->email;
	}

	public function setEmail(?string $email): void
	{
		$this->email = $email;
	}

	/**
	 * @return array<int, array<array-key, int|string>>
	 */
	public function rules(): array
	{
		return [
			[
				'country',
				'string',
				'min' => 2,
				'max' => 2,
			],
		];
	}

	public static function fromCommerceAddress(CommerceOrder $commerceOrder, ?CraftAddress $commerceAddress, bool $includeEmail): ?self
	{
		if (! $commerceAddress instanceof CraftAddress) {
			return null;
		}

		$phoneNumberFieldHandle = Plugin::getInstance()?->settings->phoneNumberFieldHandle;
		$phone = $commerceAddress->{$phoneNumberFieldHandle} ?? null;

		$address = new self([
			'name' => $commerceAddress->fullName
				?? $commerceOrder->getCustomer()?->fullName
				?? 'Unknown',
			'phone' => $phone,
			'company' => $commerceAddress->organization,
			'address1' => $commerceAddress->addressLine1,
			'address2' => $commerceAddress->addressLine2,
			'city' => $commerceAddress->locality,
			'state' => $commerceAddress->administrativeArea,
			'postalCode' => $commerceAddress->postalCode,
			'country' => $commerceAddress->countryCode,
		]);

		if ($includeEmail) {
			$address->setEmail($commerceOrder->email);
		}

		return $address;
	}
}
