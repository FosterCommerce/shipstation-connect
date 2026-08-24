# Troubleshooting

Orders that never arrive, connections that fail, and tracking numbers that go missing.

Everything the plugin drops or skips is written to the Craft log under the `shipstationconnect` category. Check there first: it usually names the order and the reason.

## ShipStation cannot connect

**Check the URL.** It is the one shown on the settings page, ending in `/actions/shipstationconnect/orders/process`. A missing `actions` segment gives a 404.

**Check the credentials.** They are the values in the plugin settings, not a Craft user and not your ShipStation login. If the settings hold environment variable names, confirm those variables are set in the environment the site runs in, and that the values have no leading or trailing spaces.

**Confirm it from your own machine**, which separates a credential problem from a ShipStation problem:

```sh
curl -i -u "username:password" \
  "https://your-site.com/actions/shipstationconnect/orders/process?action=export"
```

A `401` means the credentials do not match. A `200` here with a failure in ShipStation points at the URL you gave ShipStation.

### 401 or 404 on Apache

ShipStation reports this as:

> The remote server returned an error

Apache does not always pass the Authorization header through to PHP, so the plugin never sees the credentials and answers 401. Add this to your Apache config:

```
CGIPassAuth On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

### Staging environments

Server level basic auth in front of Craft consumes the Authorization header before Craft sees it, so the plugin answers 401 for the same reason as above. Exempt the endpoint path from it.

## An order never reached ShipStation

Work down this list:

- **Is the order completed?** Carts are never sent. An order in the Commerce order index is completed; anything under Carts is not.
- **Did it change inside the window ShipStation asked for?** ShipStation asks for orders changed between two dates. Editing the order updates its date and brings it back into a later window, and you can trigger an import by hand from ShipStation's store list.
- **Was it dropped as invalid?** An order with no line items, no customer, no shipping address, no order status, or no billing address is left out and logged. [Order export](./order-export.md#orders-that-are-left-out) lists the full set of reasons.
- **Does its store field match?** On a multi store setup, an order whose Dropdown field is empty matches no store URL and reaches none of them.
- **Is it past the first page?** ShipStation requests page 1, reads the `pages` count off the response, and asks for the rest. A request that times out stops that walk part way. See below.

## Requests time out

Each request builds every order in the page, including reading an asset per item. Large orders and slow filesystems add up.

Lower **Page Size for Orders**. More requests, each finishing well inside the timeout, gets the whole set across where one big request does not.

## The whole export fails

**"Invalid or missing handle config"** means one of the shipping notification settings is blank. All six are required: status, Matrix field, entry type, and the three inner fields.

**An error naming one order** means **Fail export on validation failure** is on, and one order in the page could not be built. The message names the order and the field. Fix that order, or turn the setting off to send the rest of the page without it.

## An order shipped but has no tracking number

The status changed, so the notification arrived. The write into the field is what failed, and it fails quietly.

- **Check the field handles** under **ShipStation Connect -> Settings** against the field itself. The Matrix handle, entry type handle, and the three inner handles all have to match.
- **Check the field is in the order field layout**, under **Commerce -> Settings -> Order Fields**. A field that exists but is not on the layout has nowhere to store a value.
- **Check the Craft log** for "Missing shippingInfo Matrix field. Ignoring." or "Unable to save shipping information."

## Shipping notifications come back 404

The plugin looks the order up by its reference, and ShipStation quotes the value it received in `OrderNumber`.

If a module changes what goes into `OrderNumber` without also changing the lookup, nothing matches. See [changing the order number](../dev-guide/customizing-exported-orders.md#changing-the-order-number).

## Weights are wrong in ShipStation

Pounds are sent as pounds; any other Commerce weight unit is sent as grams after multiplying by 1000. A store measuring in kilograms is correct. A store already measuring in grams sends weights a thousand times too heavy, and the fix is to switch the Commerce unit under **Commerce -> Settings -> General Settings**.

## Two sites overwrite each other's orders

ShipStation keys orders on `OrderID`, which here is the Commerce order ID, an integer. Two Craft sites feeding one ShipStation account both produce an order 1042.

Set a different **Order ID Prefix** on each site.
