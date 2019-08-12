<?php
namespace fostercommerce\shipstationconnect\events;

use yii\base\Event;

class OrderFieldEvent extends Event
{
    const FIELD_ORDER_NUMBER = 'OrderNumber';
    const FIELD_SHIPPING_METHOD = 'ShippingMethod';
    const FIELD_CUSTOM_FIELD_1 = 'CustomField1';
    const FIELD_CUSTOM_FIELD_2 = 'CustomField2';
    const FIELD_CUSTOM_FIELD_3 = 'CustomField3';
    const FIELD_INTERNAL_NOTES = 'InternalNotes';
    const FIELD_CUSTOMER_NOTES = 'CustomerNotes';
    const FIELD_GIFT = 'Gift';
    const FIELD_GIFT_MESSAGE = 'GiftMessage';

    public $field;
    public $order;
    public $data = null;
    public $cdata = true;
}

