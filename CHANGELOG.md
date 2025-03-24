# Changelog

## 3.0.1 - 2025-03-24

- Fixes issue where the order ID is passed as a string instead of an int.

## 3.0.0 - 2024-11-27

- Requires PHP 8.2 or higher.
- Requires CraftCMS 5 or higher and Craft Commerce 5 or higher.
- Added `Xml::ORDER_EVENT` event.
- Added `OrderEvent`.
- Added `failOnValidation` config item and toggle in settings UI.
- Removed the `Xml::ORDER_FIELD_EVENT` event. `Xml::ORDER_EVENT` should be used instead.
- Removed `OrderFieldEvent`. All details about an order can be updated in a single event now.
- Removed support for Craft's basic authentication through a dedicated Craft user.
- Removed unnecessary Twig filters, `is_matrix`, `is_dropdown` and `is_asset`.

## 2.1.0 - 2024-05-24

### Added

- Support for a single asset field to fetch a product image for order line items during order export.

## 2.0.4 - 2024-03-22

- Update plugin branding

## 2.0.3 - 2023-09-27

### Added

- Support for setting a custom phone number field that will be included within the address info sent to Shipstation

## 2.0.2 - 2023-09-01

### Fixed

- Fixed issue where custom shipping methods were not showing up in generated XML ([#54](https://github.com/FosterCommerce/shipstation-connect/pull/54))
- 
## 2.0.1 - 2022-07-19

### Added

- Support for Craft 4/Commerce 4. Note that development for Craft 3 has stopped at 1.3.7.

## 1.3.7 - 2021-12-01

### Fixed

- Fixed issue where a fields with a column suffix would break the query fetching
  orders.

## 1.3.6 - 2021-07-09

### Fixed

- Fixed invalid format in orders XML for discounts $1,000 or higher.

## 1.3.5 - 2020-12-07

### Fixed

- Error which could occur when an order is missing an order status
[[#23](https://github.com/FosterCommerce/shipstation-connect/pull/23)].

## 1.3.4 - 2020-11-11

### Added

- Setting to configure "Shipped" order status handle.
- Add condition to ensure an order has a customer in some edge cases.

## 1.3.3 - 2020-09-14

### Updated

- Updated to work with Craft Basic Authentication when enabled.

## 1.3.2 - 2020-05-08

### Fixed

- String length limit for line item options.

## 1.3.1 - 2020-05-08

### Updated

- Aligned field string lengths with ShipStation's limits (#16).

### Added

- Support for anonymous customers when shipping/billing address doesn't have a
  name set.

## 1.3.0 - 2020-03-03

### Added

- Added composer support for Craft Commerce 3.

## 1.2.7 - 2019-12-05

### Updated

- Add back CDATA sections to item SKU and item name properties.

## 1.2.6 - 2019-12-04

### Updated

- Replaced use of deprecated adjustment functions, `getAdjustmentsTotalByType`.
- Return maximum of 200 characters for the line item `Name` field.

## 1.2.5 - 2019-11-25

### Updated

- Line Item options which are an array or object are serialized to a JSON string.
- Order Line Item options are now limited to a maximum of 10 per line item. Limit set by ShipStation.

## 1.2.4 - 2019-10-28

### Updated

- Username/password fields accept environment variables as values.

## 1.2.3 - 2019-10-22

### Added

- Option to use shipping address for billing address when billing information is missing.

## 1.2.2 - 2019-09-11

### Updated

- Order filter by subscription store to filter on the column directly instead of relying on the search index.

### Removed

- Untracked `composer.lock`.

## 1.2.1 - 2019-08-23

### Added

- `FindOrderEvent` to allow users to implement custom logic to find an order.

## 1.2.0 - 2019-08-16

### Updated

- `OrderFieldEvent::data` to `OrderFieldEvent::value` because `data` is already defined in the parent class.

## 1.1.0 - 2019-08-12

### Added

- `OrderFieldEvent` event for setting values of custom fields.
- Settings to specify which matrix field to use for setting order tracking information.
- Multiple store configuration
- Event to override default OrderNumber field
- Event to override default ShippingMethod field

### Updated

- Filter orders by date modified according to the ShipStation docs.
- Default OrderNumber field to `"reference"`

### Removed

- Field creation migrations - These need to be handled by the user manually now.

## 1.0.11 - 2019-04-01

### Removed

- Removed automatic linking of matrix field to Orders fields

## 1.0.10 - 2018-10-25

### Added

- Added a link to ShipStation as a CP sub nav item.

### Updated

- Use Craft form fields instead of regular form inputs.
- Set settings view as a sub nav item in the CP.

## 1.0.9 - 2018-10-23

### Fixed

- Fix `getShippingInfo` null reference error.
- Fix "Undefined variable" error when invalid credentials are passed to the process action.

## 1.0.8 - 2018-10-10

### Added

- Exception logging on the `process` action.
- Logging when the `shippingInfo` matrix field is not found.
- Logging when a matrix block can not be saved.

### Changed

- Updated order status message.
- Updated documentation to include Matrix field information.

### Fixed

- Fixed a bug where an unhandled exception was thrown if the Matrix field wasn't found.
- Fixed deprecation errors: Updated element queries to use newer Craft API.

## 1.0.7 - 2018-10-09

### Added

- Icon mask.
- Matrix field to store shipping information received from ShipStation on an order.

### Changed

- Updated configuration section in documentation

## 1.0.5 - 2018-09-21

### Fixed

- Fixes null reference error.

## 1.0.4 - 2018-09-21

- Public release for plugin store.
