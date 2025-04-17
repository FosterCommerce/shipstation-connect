# Changelog

## 3.0.3 - 2025-04-17

- Fixes an issue where the productImagesHandle could sometimes be an empty string instead of null.
- Fixes an issue where Custom LineItems would cause an export to fail.

## 3.0.2 - 2025-03-24

- Fixes incorrect name for an option in the item options list.

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