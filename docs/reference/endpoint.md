# Endpoint

One URL serves both directions of the ShipStation integration, split by an `action` parameter.

```
/actions/shipstationconnect/orders/process
```

## Authentication

HTTP Basic, against the **Username** and **Password** in the plugin settings. Craft sessions and Craft users play no part: the endpoint is anonymous, and CSRF validation is off.

Credentials are compared with a timing safe comparison, and both settings resolve environment variables.

```
Authorization: Basic <base64 of username:password>
```

Apache does not always pass this header to PHP. See [troubleshooting](../user-guide/troubleshooting.md#401-or-404-on-apache).

## GET /actions/shipstationconnect/orders/process?action=export

Returns completed orders as XML.

| Parameter    | Type   | Required | Description                                                                 |
|--------------|--------|----------|-----------------------------------------------------------------------------|
| `action`     | string | Yes      | `export`                                                                     |
| `start_date` | string | No       | Any format `strtotime()` parses. ShipStation sends UTC, as `MM/dd/yyyy HH:mm` in 24 hour notation, URL encoded. |
| `end_date`   | string | No       | Same format.                                                                  |
| `page`       | int    | No       | 1 based. Values below 1 or non-numeric are treated as 1. Default: `1`.       |
| `store`      | string | No       | Matches against the Dropdown field named in **Select ShipStation Store field**. Ignored when no field is selected. |

Invariants:

- Only completed orders are returned, ordered by `dateUpdated` ascending.
- The date filter applies only when both `start_date` and `end_date` are given. One alone is ignored.
- The range is exclusive at both ends. `start_date` is floored to the start of its minute, `end_date` to second 59 of its minute.
- Both are parsed in the site's system timezone, while ShipStation sends them in UTC. On a site whose timezone is not UTC, the window is offset by that difference.
- Page size comes from **Page Size for Orders**. A non-numeric or negative setting falls back to 25.
- Orders that fail validation are omitted, unless **Fail export on validation failure** is on, in which case the request fails.
- Omitting `store` returns orders from every store, not none.

Response `200`, `Content-Type: text/xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<Orders pages="4">
  <Order>
    <OrderID>1042</OrderID>
    <OrderNumber>3f0a12c9</OrderNumber>
    <OrderStatus>processing</OrderStatus>
    <OrderTotal>128.5</OrderTotal>
    <TaxAmount>8.5</TaxAmount>
    <ShippingAmount>10</ShippingAmount>
    <LastModified>8/24/2026 09:14</LastModified>
    <PaymentMethod>Visa ending 4242</PaymentMethod>
    <ShippingMethod>standard</ShippingMethod>
    <Items>
      <Item>
        <SKU>TS-BLK-M</SKU>
        <Name>Cotton T-Shirt, Black, Medium</Name>
        <Weight>200</Weight>
        <Quantity>2</Quantity>
        <UnitPrice>55</UnitPrice>
        <ImageUrl>https://your-site.com/uploads/ts-blk.jpg</ImageUrl>
        <WeightUnits>Grams</WeightUnits>
        <Adjustment>false</Adjustment>
        <Options>
          <Option>
            <Name>giftWrap</Name>
            <Value>yes</Value>
          </Option>
        </Options>
      </Item>
    </Items>
    <Customer>
      <CustomerCode>318</CustomerCode>
      <BillTo>
        <Name>Dana Reyes</Name>
        <Address1>14 Mill Lane</Address1>
        <City>Portland</City>
        <State>OR</State>
        <PostalCode>97205</PostalCode>
        <Country>US</Country>
        <Phone>5035550142</Phone>
        <Email>dana@example.com</Email>
      </BillTo>
      <ShipTo>...</ShipTo>
    </Customer>
    <InternalNotes></InternalNotes>
    <Gift>false</Gift>
    <OrderDate>8/23/2026 16:02</OrderDate>
    <CustomField1></CustomField1>
    <CustomField2></CustomField2>
    <CustomField3></CustomField3>
    <CustomerNotes></CustomerNotes>
    <GiftMessage></GiftMessage>
  </Order>
</Orders>
```

See [XML fields](./xml-fields.md) for every field and its source.

```sh
curl -u "$SHIPSTATION_USERNAME:$SHIPSTATION_PASSWORD" \
  "https://your-site.com/actions/shipstationconnect/orders/process?action=export&start_date=8%2F1%2F2026+00%3A00&end_date=8%2F24%2F2026+23%3A59&page=1"
```

## POST /actions/shipstationconnect/orders/process?action=shipnotify

Marks an order shipped and records its tracking details. Parameters are read from the query string or the body.

| Parameter         | Type   | Required | Description                                          |
|-------------------|--------|----------|------------------------------------------------------|
| `action`          | string | Yes      | `shipnotify`                                          |
| `order_number`    | string | Yes      | Matched against the Commerce order reference.         |
| `carrier`         | string | No       | Written to the Carrier field.                         |
| `service`         | string | No       | Written to the Service field.                         |
| `tracking_number` | string | No       | Written to the Tracking Number field.                 |

Invariants:

- The order moves to the status in **Status Handle** whether or not any tracking value is present.
- One entry per order. A second notification overwrites the first.
- An entry is created only when at least one of the three values is non-empty.
- A missing Matrix field is logged and skipped; the status change still goes through.
- The order is saved without validation, so a notification cannot be blocked by an order that no longer validates.

Response `200`:

```json
{
  "success": true
}
```

```sh
curl -u "$SHIPSTATION_USERNAME:$SHIPSTATION_PASSWORD" \
  -X POST \
  "https://your-site.com/actions/shipstationconnect/orders/process?action=shipnotify" \
  -d "order_number=3f0a12c9" \
  -d "carrier=USPS" \
  -d "service=Priority Mail" \
  -d "tracking_number=9400111899223197428490"
```

## Errors

| Status | Cause                                                                              |
|--------|-------------------------------------------------------------------------------------|
| `400`  | `action` missing, or not `export` or `shipnotify`                                    |
| `401`  | Username or password did not match                                                   |
| `404`  | No order matches `order_number`                                                      |
| `406`  | `order_number` was missing or empty                                                  |
| `500`  | One of the shipping notification handle settings is blank, the shipped status handle does not exist, an order could not be saved, or an order failed validation with **Fail export on validation failure** on |

Errors come back in Craft's own error format, which is an HTML page by default and JSON when the request asks for JSON. The Craft log is where the reason is.
