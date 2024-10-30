<?php

namespace fostercommerce\shipstationconnect\models;

use Symfony\Component\Serializer\Annotation\SerializedName;

class Option extends Base
{
	#[SerializedName('Name')]
	public string $name;

	#[SerializedName('Value')]
	public string $value;
}
