# XML fields

Every field in the export, where its value comes from, and its length limit.

Strings over the limit are cut to length when the document is built, without an error. Fields marked "Empty" are always sent empty and exist for [a module to fill](../dev-guide/customizing-exported-orders.md).

## Document

```xml
<Orders pages="4">
  <Order>...</Order>
</Orders>
```

`pages` is the total number of pages for the query, not the page number. ShipStation starts at page 1 and requests the rest until it has them all.

## Order

| Field            | Source                                                        | Limit |
|------------------|---------------------------------------------------------------|-------|
| `OrderID`        | Order ID Prefix setting, then the Commerce order ID            | 50    |
| `OrderNumber`    | `reference`, the order's human readable number                 | 50    |
| `OrderStatus`    | Order status handle                                            | 50    |
| `OrderTotal`     | `totalPrice`, rounded to 2 decimal places                      |       |
| `TaxAmount`      | `getTotalTax()`                                                |       |
| `ShippingAmount` | `getTotalShippingCost()`                                       |       |
| `OrderDate`      | `dateOrdered`, falling back to `dateCreated`                   |       |
| `LastModified`   | `dateUpdated`, falling back to `dateCreated`                   |       |
| `PaymentMethod`  | The payment source description. Empty on orders paid without a stored source | 50 |
| `ShippingMethod` | `shippingMethodHandle`, not the method's name                  | 100   |
| `Items`          | One `Item` per line item, plus one for the discount            |       |
| `Customer`       | The customer and both addresses                                |       |
| `InternalNotes`  | Empty                                                          | 1000  |
| `CustomerNotes`  | Empty                                                          | 1000  |
| `GiftMessage`    | Empty                                                          | 1000  |
| `Gift`           | `false`                                                        |       |
| `CustomField1`   | Empty                                                          | 100   |
| `CustomField2`   | Empty                                                          | 100   |
| `CustomField3`   | Empty                                                          | 100   |

Both dates are formatted `n/j/Y H:i`, in the site's system timezone, with no timezone marker. ShipStation reads a date that carries no timezone as UTC.

An order missing `OrderStatus` or `Customer` is dropped from the response. See [orders that are left out](../user-guide/order-export.md#orders-that-are-left-out).

## Item

| Field         | Source                                                                    | Limit |
|---------------|---------------------------------------------------------------------------|-------|
| `SKU`         | The SKU in the line item snapshot, falling back to the line item's own SKU | 100   |
| `Name`        | The line item description                                                  | 200   |
| `Quantity`    | `qty`                                                                      |       |
| `UnitPrice`   | `salePrice`, rounded to 2 decimal places                                   |       |
| `Weight`      | The line item weight, converted to `WeightUnits`                           |       |
| `WeightUnits` | `Pounds` when Commerce is set to pounds, otherwise `Grams`                  |       |
| `ImageUrl`    | The first asset in the Product Images field, read off the variant and falling back to the product. Empty when unset, when the purchasable has no asset, or on custom line items |  |
| `Adjustment`  | `false` on line items, `true` on the discount item                          |       |
| `Options`     | One `Option` per line item option                                           |       |

Any Commerce weight unit other than pounds is converted by multiplying by 1000, which is correct from kilograms and wrong from grams.

### The discount item

An order with a non-zero total discount carries one extra item:

| Field        | Value                                          |
|--------------|------------------------------------------------|
| `SKU`        | Empty                                          |
| `Name`       | `couponCode`                                   |
| `Quantity`   | `1`                                            |
| `UnitPrice`  | The order's total discount, normally negative  |
| `Adjustment` | `true`                                         |

Discounts are not itemized. The order's whole discount arrives as this one figure.

## Option

| Field   | Source                       | Limit |
|---------|------------------------------|-------|
| `Name`  | The line item option key     | 100   |
| `Value` | The line item option value   | 100   |

A value that is neither a string nor stringable is JSON encoded before the limit is applied.

ShipStation accepts 10 `Option` nodes per `Item`. The plugin sends one per line item option, with no cap.

## Customer

| Field          | Source                                                                     | Limit |
|----------------|----------------------------------------------------------------------------|-------|
| `CustomerCode` | The Craft user ID of the order's customer                                   | 50    |
| `BillTo`       | The billing address. Falls back to the shipping address when **Billing address same as shipping address** is on | |
| `ShipTo`       | The shipping address                                                        |       |

## Address

| Field        | Source                                                                      | Limit |
|--------------|------------------------------------------------------------------------------|-------|
| `Name`       | The address `fullName`, falling back to the customer's `fullName`, then `Unknown` | 100 |
| `Company`    | `organization`                                                               | 100   |
| `Address1`   | `addressLine1`                                                               | 200   |
| `Address2`   | `addressLine2`                                                               | 200   |
| `City`       | `locality`                                                                   | 100   |
| `State`      | `administrativeArea`                                                         | 100   |
| `PostalCode` | `postalCode`                                                                 | 50    |
| `Country`    | `countryCode`                                                                | 2     |
| `Phone`      | The address field named in **Phone number field handle**                     | 50    |
| `Email`      | The order email. Sent on `BillTo` only                                       |       |

`addressLine3` is not sent. `Country` is validated rather than truncated: an address whose country code is not exactly two characters drops the order from the response.
