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

After installing with composer, go to the Craft control panel plugin settings page to install and configure the settings for the plugin.

## Custom Store Configuration

Configure your connection in ShipStation following these instructions: [ShipStation "Custom Store" integration](https://help.shipstation.com/hc/en-us/articles/205928478-ShipStation-Custom-Store-Development-Guide#3a).

### Connect Your Craft Store to ShipStation

The "URL to Custom XML Page" is shown in the ShipStation Connect settings view in Craft.

### Username/Password

ShipStation allows you to set a custom username and password combination for a connected store. This combination should match the values stored in the ShipStation Connnect settings view in your Craft control panel.

**Note:** These values are *not* your ShipStation credentials, nor your Craft user credentials.

### Order Statuses

Ensure your shipping statuses in Craft Commerce and ShipStation match. You edit each platform to use custom statuses and ShipStation can match multiple Craft statuses to a single ShipStation status, when needed.

## Commerce Integration

ShipStation Connect will create a new Matrix field called "Shipping Info" under the "ShipStation Connect" Group. It will also automatically add a new tab to the Orders layout in Craft Commerce called "ShipStation Connect" which will include the Shipping Info field.

When a shipping notification is received for an order from ShipStation, the plugin will add the shipping information to the Shipping Information field on the order and set the order status to Shipped.

## Custom Fields

Add information to the following fields defined by ShipStation:

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

## Template Examples

Coming soon

