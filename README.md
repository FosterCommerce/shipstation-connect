# ShipStation Connect for Craft CMS 3.x and Commerce 2.x
A plugin for Craft Commerce that integrates with ShipStation.

_TODO: Update README_

## Installation

**Note: This plugin is written for Craft 2.x. It may not work in Craft 3+.**

Use [composer](https://getcomposer.org/) to install this package:

```
composer require onedesign/oneshipstation
```

After installing with composer, go to the Craft control panel plugin page to install and configure the settings for the plugin.



## Craft Configuration

### CSRF Protection

If you have CSRF protection enabled in your app, you will need to disable it for when ShipStation POSTs shipment notifications.

In `craft/config/general.php`, if you have `enableCsrfProtection` set to true (or, in Craft 3+, if you _don't_ have it set to false), you will need to add the following:

```
return array(
    //...
    'enableCsrfProtection' => !isset($_REQUEST['p']) || $_REQUEST['p'] != '/actions/oneShipStation/orders/process'
)
```

This will ensure that CSRF protection is enabled for all routes that are NOT the route ShipStation posts to.

### "Action" naming collision

ShipStation and Craft have a routing collision due to their combined use of the parameter `action`.
ShipStation sends requests using `?action=export` to read order data, and `?action=shipnotify` to update shipping data.
This conflicts with Craft's reserved word `action` to describe an ["action request"](https://craftcms.com/docs/plugins/controllers#how-controller-actions-fit-into-routing),
which is designed to allow for easier routing configuration.

Because of this, the route given to ShipStation for their Custom Store integration _must_ begin with your Craft config's "actionTrigger" (in `craft/config/general.php`), which defaults to the string "actions".

For example, if your actionTrigger is set to "actions", the URL you prove to ShipStation should be:

```
https://{yourdomain.com}/actions/oneShipStation/orders/process
```

If your actionTrigger is set to "myCustomActionTrigger", it would be:

```
https://{yourdomain.com}/myCustomActionTrigger/oneShipStation/orders/process
```

**Note: the above URL is case sensitive**! Due to Craft's segment matching, the `oneShipStation` segment in the URL _must_ be `oneShipStation`, not `oneshipstation` or `ONESHIPSTATION`.



## ShipStation Configuration

### Order Statuses

When ShipStation marks an order as shipped it calls back to Craft to update the order. This plugin assumes that there is an order status defined in Craft Commerce with the handle "shipped".

### Authentication

Once you have configured your Craft application's OneShipStation, you will need to complete the process by configuring your [ShipStation "Custom Store" integration](https://help.shipstation.com/hc/en-us/articles/205928478-ShipStation-Custom-Store-Development-Guide#3a).

There, you will be required to provide a user name, password, and a URL that ShipStation will use to contact your application. These can be found in the plugin settings in Craft control panel.



## Using OneShipStation in your Site Templates

### Tracking Information

One ShipStation provides a helper method to add to your template to provide customers with a link to track their shipment.

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

    public function oneShipStationCustomField1() {
        return function($order) {
            return 'my custom value';
        };
    }

}
```

Note: OneShipStation will add a `CustomFieldX` child for each plugin that responds to the hook. So, to avoid overlap, be sure to only use one hook.

For internal notes, if a plugin responds to the hook at all, the key will be added. Respond as:

```
class MyPlugin extends BasePlugin {

    public function oneShipStationInternalNotes() {
        return function($order) {
            return 'internal notes for this order';
        };
    }

}
```

### Overriding Shipping Method

By default, One Shipstation will send the shipping method handle to Shipstation for the `ShippingMethod` on each order.

You can override this very much like you'd add a custom field or internal notes above. In your plugin, define a function `oneShipStationShippingMethod()`,
which returns a callback that takes an order and returns a string. For example, if you want the ShippingMethod to be called `Ground` for all customers in the US,
you could declare a method as follows:

```
class MyPlugin extends BasePlugin {

    public function oneShipStationShippingMethod() {
        return function($order) {
            if ($order->getShippingAddress()->country == 'US') {
                return 'Ground';
            }
        }
    }

}
```

If you return null or void, OneShipstation will assign the shipping method to be the shipping method handle, as default.

### Overriding Tracking URLs

Currently One ShipStation only provides links for common carriers. If your carrier is not defined, or if you want a different URL, you can override:

```
class MyPlugin extends BasePlugin {

    public function oneShipStation_trackingURL($shippingInfo) {
        return 'https://mycustomlink?tracking=' . urlencode($shippingInfo->trackingNumber);
    }

}
```


## Development

On any Craft 2.x project, navigate to `craft/plugins` and clone the repository:

```
$ cd craft/plugins
$ git clone git@github.com:onedesign/oneshipstation.git
```

Be sure to add `craft/plugins/oneshipstation` to your other project's gitignore, if applicable:

```
# .gitignore
craft/plugins/oneshipstation
```

## Bugs / Issues

Submit bug reports and issues via https://github.com/onedesign/oneshipstation/issues. Please be as thorough as possible when submitting bug reports.


## Contributing

1. Fork the repo on GitHub
2. Clone the project to your own machine
3. Commit changes to your own branch
4. Push your work back up to your fork
5. Submit a Pull request so that we can review your changes

NOTE: Be sure to merge the latest from "upstream" before making a pull request!

