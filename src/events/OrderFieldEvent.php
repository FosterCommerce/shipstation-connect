<?php

namespace fostercommerce\shipstationconnect\events;

use craft\commerce\elements\Order;
use yii\base\Event;

/**
 * @deprecated
 */
class OrderFieldEvent extends Event
{
	/**
	 * @var string
	 */
	public const FIELD_ORDER_NUMBER = 'OrderNumber';

	/**
	 * @var string
	 */
	public const FIELD_SHIPPING_METHOD = 'ShippingMethod';

	/**
	 * @var string
	 */
	public const FIELD_CUSTOM_FIELD_1 = 'CustomField1';

	/**
	 * @var string
	 */
	public const FIELD_CUSTOM_FIELD_2 = 'CustomField2';

	/**
	 * @var string
	 */
	public const FIELD_CUSTOM_FIELD_3 = 'CustomField3';

	/**
	 * @var string
	 */
	public const FIELD_INTERNAL_NOTES = 'InternalNotes';

	/**
	 * @var string
	 */
	public const FIELD_CUSTOMER_NOTES = 'CustomerNotes';

	/**
	 * @var string
	 */
	public const FIELD_GIFT = 'Gift';

	/**
	 * @var string
	 */
	public const FIELD_GIFT_MESSAGE = 'GiftMessage';

	public string $field;

	public Order $order;

	public mixed $value;

	public bool $cdata = true;
}
