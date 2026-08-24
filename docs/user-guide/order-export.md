# Order export

Which orders reach ShipStation, what each one carries, and how to run more than one ShipStation store.

## When orders go out

ShipStation pulls. Nothing is pushed, queued, or stored: each request reads Commerce live and returns what it finds.

ShipStation asks on its own schedule, for orders changed between two dates, and you can trigger an import by hand from its store list. An order already sent once is sent again whenever it changes and falls inside a later range.

An order is included when it is **completed** in Commerce, that is, when the cart became an order. Carts are never sent. Order status has no effect on whether an order is included: the status handle goes out as a field, and a canceled order is sent like any other.

Orders are sent oldest first, by the date they last changed, in pages of 25. Change the page size under **ShipStation Connect -> Settings**.

## What each order carries

- Order ID, order number, status handle, and both dates
- Order total, tax, and shipping cost
- Payment method and shipping method
- One item per line item: SKU, name, quantity, unit price, weight, photo, and options
- The customer, with shipping and billing addresses, phone number, and email

Notes, gift flags, and ShipStation's three custom fields are sent empty unless a developer fills them. See [customizing exported orders](../dev-guide/customizing-exported-orders.md).

[XML fields](../reference/xml-fields.md) lists every field, where its value comes from, and its length limit.

### Discounts

An order with a discount gets an extra item named `couponCode`, carrying the discount as a negative unit price and flagged as an adjustment, so the items add up to the order total.

Individual discounts are not itemized. The item carries the order's total discount as one figure.

### Product photos

Set **Product Images Field Handle** to your Assets field and each item carries the first asset on its variant, falling back to the asset on the product when the variant has none. Items whose purchasable has no image, and custom line items, go out without one.

### Item options

Line item options are sent as name and value pairs. Both are cut to 100 characters. An option holding something other than text, such as an array of add-ons, is sent as JSON.

### Weights

Weights come from the line item, in the unit set under **Commerce -> Settings -> General Settings**. Pounds are sent as pounds. Any other unit is sent as grams, converted by multiplying by 1000, so a store measuring in kilograms is correct and a store already measuring in grams is not.

### Phone numbers

Craft addresses have no phone field. Add a custom field to your address field layout, then name its handle in **Phone number field handle**. Its value is sent on both the shipping and billing address.

Leave it unset and ShipStation gets no phone number, on either address.

## Orders that are left out

An order that cannot produce a valid ShipStation record is dropped from the response, and the rest of the page is sent. The reason is written to the Craft log. An order is dropped when it has:

- No line items
- No customer
- No shipping address
- No billing address, unless **Billing address same as shipping address** is on
- No order status
- A country on either address that is not a two letter code

Turn on **Fail export on validation failure** to get an error instead, which stops ShipStation ingesting a partial page. See [troubleshooting](./troubleshooting.md#an-order-never-reached-shipstation).

## Order IDs and order numbers

Two identifiers go out, and they are not the same thing:

- **OrderID** is the Commerce order ID, with your optional prefix. ShipStation uses it as the order's unique identifier and never displays it.
- **OrderNumber** is the order reference, the order number ShipStation shows.

Shipping notifications come back quoting the order number, and the plugin finds the order by its reference. Change what goes into `OrderNumber` and notifications stop matching, unless you also change the lookup. See [customizing exported orders](../dev-guide/customizing-exported-orders.md#changing-the-order-number).

The order ID is an integer, so two Craft sites both produce an order 1042, and ShipStation treats them as the same order. Set a different **Order ID Prefix** on each.

## Multiple ShipStation stores

One Craft site can feed several ShipStation stores, split by a field on the order.

1. Create a Dropdown field, with one option per ShipStation store, and add it to the order field layout under **Commerce -> Settings -> Order Fields**.
2. Set that value on each order, from your checkout or a module.
3. Select the field in **Select ShipStation Store field** under **ShipStation Connect -> Settings**.

The settings page then lists one URL per dropdown option, each carrying a `store` parameter. Give each ShipStation store its own URL, and it sees only the orders whose field matches.

All the stores share one username and password.

Orders with the field empty match no store URL and reach none of them.
