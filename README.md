# ShipStation Connect for Craft CMS 3.x and Commerce 2.x
A plugin for Craft Commerce that integrates with a ShipStation Custom Store.

_TODO: Update README_

## Requirements

This plugin requires Craft CMS 3.x and Commerce 2.x or later

## Installation

Install ShipStation Connect from the Plugin Store or with Composer

### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “ShipStation Connect.” Click on the “Install” button in its modal window.

### With Composer

Open your terminal (command line) and run the following commands:

```
# go to the project directory
cd /path/to/my-project

# tell Composer to load the plugin
composer require fostercommerce/shipstationconnect

# tell Craft to install the plugin
./craft install/plugin shipstationconnect
```

After installing with composer, go to the Craft control panel plugin settings page to install and configure the settings for the plugin.

## Configuration

### Order Statuses

Ensure you have an order status in Craft Commerce with the handle "shipped." This is necessary because ShipStation uses the "shipped" label.

### Connect Your Craft Store to ShipStation

Configure your connection in ShipStation following these instructions: [ShipStation "Custom Store" integration](https://help.shipstation.com/hc/en-us/articles/205928478-ShipStation-Custom-Store-Development-Guide#3a).

The "URL to Custom XML Page" is shown in the ShipStation Connect settings view in Craft.

## Template Examples

### Tracking Information

ShipStation Connect provides a helper method to add to your template to provide customers with a link to track their shipment.

```
{% for shipmentInfo in order.shippingInfo %}
  {% set tracking = craft.oneShipStation.trackingNumberLinkHTML(shipmentInfo) %}
  {% if tracking|length %}
    Track shipment: {{ tracking|raw }}
  {% endif %}
{% endfor %}
```

## Hooks / Customizing

### Custom Fields & Order Notes

Shipstation allows the adding extra data to orders. These fields appear in ShipStation as `customField1`, `customField2`, `customField3`, `internalNotes`, `customerNotes`, `gift` and `giftMessage`.

You can populate these fields by ["latching on" to a Craft hook](https://craftcms.com/docs/plugins/hooks-and-events#latching-onto-hooks) in your custom site plugin.

Your plugin should return a callback that takes a single parameter `$order`, which is the order instance. It should return a single value.

In this example, the plugin `MyPlugin` will send the value `my custom value` to all orders in the `customField1` param to ShipStation:

```
class MyPlugin extends BasePlugin {

    public function shipStationConnectCustomField1() {
        return function($order) {
            return 'my custom value';
        };
    }

}
```

Note: ShipStation Connect will add a `CustomFieldX` child for each plugin that responds to the hook. So, to avoid overlap, be sure to only use one hook.

For internal notes, if a plugin responds to the hook at all, the key will be added. Respond as:

```
class MyPlugin extends BasePlugin {

    public function shipStationConnectInternalNotes() {
        return function($order) {
            return 'internal notes for this order';
        };
    }

}
```

### Overriding Shipping Method

By default, Shipstation Connect will send the shipping method handle to Shipstation for the `ShippingMethod` on each order.

You can override this very much like you'd add a custom field or internal notes above. In your plugin, define a function `shipStationConnect
ShippingMethod()`,
which returns a callback that takes an order and returns a string. For example, if you want the ShippingMethod to be called `Ground` for all customers in the US,
you could declare a method as follows:

```
class MyPlugin extends BasePlugin {

    public function shipStationConnectShippingMethod() {
        return function($order) {
            if ($order->getShippingAddress()->country == 'US') {
                return 'Ground';
            }
        }
    }

}
```

If you return null or void, Shipstation Connect will assign the shipping method to be the shipping method handle, as default.

### Overriding Tracking URLs

Currently, ShipStation Connect only provides links for common carriers. If your carrier is not defined, or if you want a different URL, you can override:

```
class MyPlugin extends BasePlugin {

    public function shipStationConnect_trackingURL($shippingInfo) {
        return 'https://mycustomlink?tracking=' . urlencode($shippingInfo->trackingNumber);
    }

}
```


## Bugs / Issues

Submit bug reports and issues via https://github.com/fostercommerce/shipstation-connect/issues. Please be as thorough as possible when submitting bug reports.


## Contributing

1. Fork the repo on GitHub
2. Clone the project to your own machine
3. Commit changes to your own branch
4. Push your work back up to your fork
5. Submit a Pull request so that we can review your changes

NOTE: Be sure to merge the latest from "upstream" before making a pull request!

