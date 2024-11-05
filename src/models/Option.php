<?php

namespace fostercommerce\shipstationconnect\models;

use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;

class Option extends Base
{
	#[Groups(['export'])]
	#[SerializedName('Name')]
	private string $name;

	#[Groups(['export'])]
	#[SerializedName('Value')]
	private string $value;

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): void
	{
		$this->name = $name;
	}

	public function getValue(): string
	{
		return $this->value;
	}

	public function setValue(string $value): void
	{
		$this->value = $value;
	}
}
