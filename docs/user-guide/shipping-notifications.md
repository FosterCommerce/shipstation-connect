# Shipping notifications

What happens in Craft when an order ships from ShipStation.

## What arrives

When ShipStation reports a shipment, it calls the same URL it pulls orders from and sends four values: the order number, the carrier, the service, and the tracking number.

ShipStation sends more than that. The plugin keeps these four and ignores the rest.

## What the plugin does with it

1. Finds the Commerce order whose reference matches the order number. No match is a 404 back to ShipStation, and nothing changes in Craft.
2. Moves the order to the status set in **Status Handle**, `shipped` by default.
3. Writes the carrier, service, and tracking number into the Shipping Info Matrix field on the order.
4. Records "Marking order as shipped. Adding shipping information." in the order's activity log.

The status change is what fires your shipped-order email, if you have one set up under **Commerce -> Settings -> Emails**.

## One set of details per order

The field holds one entry per order. A second notification for the same order overwrites the first rather than adding to it.

An order that produces two notifications therefore ends up showing the last tracking number only. Storing every shipment separately needs a module listening for the notification; the plugin does not do it.

## Reading the values

The field is an ordinary Matrix field, so templates and emails read it like any other:

```twig
{% set shippingInfo = order.shippingInfo.one() %}

{% if shippingInfo %}
  <p>
    Shipped by {{ shippingInfo.carrier }} {{ shippingInfo.service }}.
    Tracking number: {{ shippingInfo.trackingNumber }}
  </p>
{% endif %}
```

Swap `shippingInfo` for your own field handle, and the three inner handles for whatever you set in the plugin settings.

## When values are missing

A notification carrying none of the three values still moves the order to the shipped status, but writes nothing. No empty entry is created.

If the Matrix field named in the settings does not exist, the order still moves to the shipped status, and a warning is written to the Craft log. Nothing tells you in the control panel, so an order that reaches shipped with an empty Shipping Info field is worth checking against [troubleshooting](./troubleshooting.md#an-order-shipped-but-has-no-tracking-number).
