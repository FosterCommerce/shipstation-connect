# Changelog

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
