<?php

namespace fostercommerce\shipstationconnect\models;

use craft\base\Model;
use craft\commerce\elements\Order as CommerceOrder;
use fostercommerce\shipstationconnect\events\OrderFieldEvent;
use fostercommerce\shipstationconnect\services\Xml;
use yii\base\Event;

abstract class Base extends Model
{
	public static function valueFromFieldEvent(string $eventField, CommerceOrder $commerceOrder, mixed $defaultValue = null): mixed
	{
		$orderFieldEvent = new OrderFieldEvent([
			'field' => $eventField,
			'order' => $commerceOrder,
			'value' => $defaultValue,
		]);

		Event::trigger(static::class, Xml::ORDER_FIELD_EVENT, $orderFieldEvent);

		$value = $orderFieldEvent->value;
		return empty($value) ? '' : $value;
	}

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
