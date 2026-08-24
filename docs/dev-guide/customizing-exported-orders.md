# Customizing exported orders

Rewrite any field before it is sent to ShipStation, from a site module or a companion plugin.

Every order fires `Xml::ORDER_EVENT` after it is built from its Commerce order and before it is serialized. `$event->order` is the transformed order, with a setter per field in the export, and `getParent()` returns the Commerce order behind it. See [order export](../user-guide/order-export.md) for what is sent by default, [XML fields](../reference/xml-fields.md) for the fields themselves, and [events](../reference/events.md) for the payloads.

Four fields exist only for you to fill, and the plugin always sends them empty: `setCustomField1()` through `setCustomField3()` (100 characters each, shown in ShipStation's orders grid and usable in its filter and automation rule criteria), and `setInternalNotes()` (1000 characters, private to your company). `setCustomerNotes()`, `setGiftMessage()`, and `setGift()` are also yours, since Commerce has no native equivalent.

## A module that fills them

```php
<?php

declare(strict_types=1);

namespace modules\shipping;

use craft\commerce\helpers\Currency;
use fostercommerce\shipstationconnect\events\OrderEvent;
use fostercommerce\shipstationconnect\services\Xml;
use yii\base\Event;
use yii\base\Module;

class ShippingModule extends Module
{
    public function init(): void
    {
        parent::init();

        Event::on(
            Xml::class,
            Xml::ORDER_EVENT,
            static function (OrderEvent $event): void {
                $order = $event->order;
                $commerceOrder = $order->getParent();

                $order->setCustomField1(
                    Currency::formatAsCurrency($commerceOrder->getAdjustmentsTotal(), 'USD')
                );

                $isGift = (bool) $commerceOrder->getFieldValue('isGift');
                $order->setGift($isGift);

                if ($isGift) {
                    $order->setGiftMessage((string) $commerceOrder->getFieldValue('giftMessage'));
                }
            }
        );
    }
}
```

Register the module in `config/app.php` as you would any other.

## What the event can and cannot do

It fires per order, once the items and customer are built, and before the order is validated. That ordering has consequences:

- **Values you set are validated.** Blanking the customer or the order status drops the order from the response, and with **Fail export on validation failure** on, fails the whole page.
- **Values you set are truncated, not rejected.** Length limits are applied when the order is serialized, so an over-long value is cut with no error.
- **The order is not saved.** Nothing you set here touches Commerce.
- **You cannot skip an order.** Deciding what qualifies belongs upstream, in the store field or the order's completion.

## Changing the order number

`OrderNumber` carries the Commerce order reference, and shipping notifications come back quoting it, which the plugin resolves with `Order::find()->reference($orderNumber)`. Change one and you have to change the other.

```php
Event::on(
    Xml::class,
    Xml::ORDER_EVENT,
    static function (OrderEvent $event): void {
        $event->order->setOrderNumber((string) $event->order->getParent()->id);
    }
);

Event::on(
    OrdersController::class,
    OrdersController::FIND_ORDER_EVENT,
    static function (FindOrderEvent $event): void {
        $event->order = CommerceOrder::find()->id($event->orderNumber)->one();
    }
);
```

Leaving `$event->order` null falls back to the reference lookup, so a listener can resolve the numbers it recognizes and pass the rest through. Orders exported before the change still quote the old value, so handle both formats while they are still shipping.

## Migrating from version 2

`OrderFieldEvent` and `Xml::ORDER_FIELD_EVENT` were removed in 3.0.0. The class remains in the codebase, deprecated, and nothing triggers it, so a listener on it fails silently.

It fired once per field, with the field name in `$event->field` and the value in `$event->value`. Replace the whole set with one `Xml::ORDER_EVENT` listener calling the matching setter per field.
