# Events

Every event the plugin exposes, what fires it, what is on the payload, and how to listen.

Both are class level events, so `Event::on()` with the class name catches every fire.

## `Xml::ORDER_EVENT`

**Fires:** in `Xml::generateXml()`, once per order, after the order is built from its Commerce order and before it is validated and serialized. Orders with no line items are skipped before this point and never fire it.

**Payload:** `fostercommerce\shipstationconnect\events\OrderEvent`

| Property | Type                                             | Notes                                                                  |
|----------|--------------------------------------------------|------------------------------------------------------------------------|
| `order`  | `fostercommerce\shipstationconnect\models\Order` | The transformed order. Mutate it, or assign a different one. `getParent()` returns the Commerce order it came from. |

**Listen:**

```php
use fostercommerce\shipstationconnect\events\OrderEvent;
use fostercommerce\shipstationconnect\services\Xml;
use yii\base\Event;

Event::on(
    Xml::class,
    Xml::ORDER_EVENT,
    static function (OrderEvent $event): void {
        $commerceOrder = $event->order->getParent();

        // Warehouse staff work from this, customers never see it.
        $event->order->setInternalNotes(
            $commerceOrder->getShippingMethod()?->name ?? 'No shipping method'
        );
    }
);
```

**Common use:** fill `CustomField1` through `CustomField3` and `InternalNotes`, which the plugin always sends empty, or overwrite a field the plugin derives.

Whatever you set is validated and truncated the same as a plugin supplied value. See [customizing exported orders](../dev-guide/customizing-exported-orders.md#what-the-event-can-and-cannot-do).

## `OrdersController::FIND_ORDER_EVENT`

**Fires:** in `OrdersController::orderFromParams()`, when a shipping notification arrives, before the plugin runs its own lookup.

**Payload:** `fostercommerce\shipstationconnect\events\FindOrderEvent`

| Property      | Type                                  | Notes                                                                 |
|---------------|---------------------------------------|-----------------------------------------------------------------------|
| `orderNumber` | `string`                              | The `order_number` ShipStation sent. Never empty.                     |
| `order`       | `?craft\commerce\elements\Order`      | Null on fire. Set it to take over the lookup; leave it null to fall through to `Order::find()->reference($orderNumber)`. |

**Listen:**

```php
use craft\commerce\elements\Order as CommerceOrder;
use fostercommerce\shipstationconnect\controllers\OrdersController;
use fostercommerce\shipstationconnect\events\FindOrderEvent;
use yii\base\Event;

Event::on(
    OrdersController::class,
    OrdersController::FIND_ORDER_EVENT,
    static function (FindOrderEvent $event): void {
        // Orders exported before the switch to IDs still quote a reference.
        if (! is_numeric($event->orderNumber)) {
            return;
        }

        $event->order = CommerceOrder::find()->id((int) $event->orderNumber)->one();
    }
);
```

**Common use:** matching notifications after a module has changed what goes into `OrderNumber`. See [changing the order number](../dev-guide/customizing-exported-orders.md#changing-the-order-number).

Leaving `order` null when you cannot resolve the number keeps the default lookup, and a genuinely unknown order still returns a 404.

## Removed

`Xml::ORDER_FIELD_EVENT` and `OrderFieldEvent` were removed in 3.0.0. Nothing triggers them, and a listener registered against them never fires and never errors. Use `Xml::ORDER_EVENT`.
