<?php

namespace fostercommerce\shipstationconnect\models;

use craft\base\Model;

abstract class Base extends Model
{
	/**
	 * @throws \JsonException
	 */
	public static function asString(mixed $value): string
	{
		if ($value instanceof \Stringable) {
			return (string) $value;
		}

		return json_encode($value, JSON_THROW_ON_ERROR);
	}
}
