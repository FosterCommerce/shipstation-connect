<?php

namespace fostercommerce\shipstationconnect\models;

use craft\base\Model;

abstract class Base extends Model
{
	/**
	 * @var int
	 */
	public const STRING_50 = 50;

	/**
	 * @var int
	 */
	public const STRING_100 = 100;

	/**
	 * @var int
	 */
	public const STRING_200 = 200;

	/**
	 * @var int
	 */
	public const STRING_1000 = 1000;

	protected function limitString(mixed $value, int $limit): string
	{
		if ($value === null || is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
			$stringValue = (string) $value;
		} else {
			$stringValue = json_encode($value, JSON_THROW_ON_ERROR);
		}

		return htmlspecialchars(
			substr(
				$stringValue,
				0,
				$limit
			)
		);
	}

	protected function limitOptionalString(mixed $value, int $limit): ?string
	{
		if ($value === null) {
			return null;
		}

		return $this->limitString($value, $limit);
	}
}
