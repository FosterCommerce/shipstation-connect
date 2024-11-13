<?php

namespace fostercommerce\shipstationconnect\models;

use JMS\Serializer\Annotation\Accessor;
use JMS\Serializer\Annotation\AccessType;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;

#[AccessType([
	'type' => 'public_method',
])]
class Option extends Base
{
	#[Groups(['export'])]
	#[SerializedName('Name')]
	#[Accessor([
		'getter' => 'getName',
		'setter' => 'setName',
	])]
	private string $name;

	#[Groups(['export'])]
	#[SerializedName('Value')]
	#[Accessor([
		'getter' => 'getValue',
		'setter' => 'setValue',
	])]
	private string $value;

	public function getName(): string
	{
		return $this->limitString($this->name, static::STRING_100);
	}

	public function setName(string $name): void
	{
		$this->name = $name;
	}

	public function getValue(): string
	{
		return $this->limitString($this->value, static::STRING_100);
	}

	public function setValue(string $value): void
	{
		$this->value = $value;
	}
}
