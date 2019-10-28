# ShipStation Connect for Craft CMS 3.x and Commerce 2.x

A plugin for Craft Commerce that integrates with a ShipStation Custom Store.

## Requirements

This plugin requires Craft CMS 3.x and Commerce 2.x or later

## Installation

Install ShipStation Connect from the Plugin Store or with Composer

### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “ShipStation Connect.” Click on the “Install” button in its modal window.

### With Composer

Open your terminal (command line) and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project

# tell Composer to load the plugin
composer require fostercommerce/shipstationconnect

# tell Craft to install the plugin
./craft install/plugin shipstationconnect
```

After installing, go to the Craft control panel plugin settings page to configure the settings for the plugin.

## Custom Store Configuration

Configure your connection in ShipStation following these instructions: [ShipStation "Custom Store" integration](https://help.shipstation.com/hc/en-us/articles/360025856192-Custom-Store-Development-Guide#UUID-685007d9-4cda-06f2-d2f6-011ab46805af_UUID-001f552d-4260-aeb0-8a23-0f6ff166e045).

### Connect Your Craft Store to ShipStation

The "URL to Custom XML Page" is shown in the ShipStation Connect settings view in Craft.

### Username/Password

ShipStation allows you to set a custom username and password combination for a connected store. This combination should match the values stored in the ShipStation Connnect settings view in your Craft control panel.

**Note:** These are *not* your ShipStation credentials, nor your Craft user credentials.

As of version 1.2.4, these values can be set with environment variables.
![Username/Password variables](screenshots/username-password-env-values.png)

### Order Statuses

Ensure your shipping statuses in Craft Commerce and ShipStation match. You edit each platform to use custom statuses and ShipStation can match multiple Craft statuses to a single ShipStation status, when needed.

## Commerce Integration

### Matrix Field

ShipStation Connect requires a Matrix Field for storing shipping information.

The matrix field should have a block type with text fields for the following:

- Carrier
- Service
- Tracking Number

![Matrix Field configuration](screenshots/matrix_field.png)

In the ShipStation Connnect settings, select the matrix field, and enter the handles for the block type and text fields.
![Shipping Info Matrix Field](screenshots/shipping-info-matrix-field.png)

When a shipping notification is received for an order from ShipStation, the plugin will add the shipping information to the Shipping Information field on the order and set the order to the Craft status paired with your ShipStation stores Shipped status.

## Custom Fields

Add information to the following fields defined by ShipStation:

- OrderNumber
- ShippingMethod
- CustomField1
- CustomField2
- CustomField3
- InternalNotes
- CustomerNotes
- Gift
- GiftMessage

Use the `OrderFieldEvent` to set the values per field:

```php
Event::on(
    Xml::class,
    Xml::ORDER_FIELD_EVENT,
    function (OrderFieldEvent $e) {
        $fieldName = $e->field;
        $order = $e->order;

        if ($fieldName === OrderFieldEvent::FIELD_GIFT) {
            $e->data = "GIFT FIELD";
            $e->cdata = false;
        } else {
            $e->data = 'OTHER FIELD';
        }
    }
);
```

`OrderFieldEvent` properties:

- `field` - The custom field name.
- `order` - Current order data.
- `data` - The data to set on this field.
- `cdata` - Whether or not to wrap the value in a CDATA block.

If you've changed the `OrderNumber` field to be anything other than the order's reference number, you'll need to listen to the `OrdersController::FIND_ORDER_EVENT` to use your own query to fetch the order. For example, if you're using the order's ID as the OrderNumber for ShipStation, you can fetch the order by ID:

```php
Event::on(
    OrdersController::class,
    OrdersController::FIND_ORDER_EVENT,
    function (FindOrderEvent $e) {
        if ($order = Order::find()->id($e->order_number)->one()) {
            $e->order = $order;
        }
    }
);
```

`FindOrderEvent` properties:

- `orderNumber` - The order number sent by ShipStation.
- `order` - The order which will be updated with shipping information.

## Template Examples

Coming soon

