<?php

namespace fostercommerce\shipstationconnect\models;

use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;

class Option extends Base
{
	#[Groups(['export'])]
	#[SerializedName('Name')]
	public string $name;

	#[Groups(['export'])]
	#[SerializedName('Value')]
	public string $value;
}
