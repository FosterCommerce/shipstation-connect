# ShipStation Connect documentation

Connects Craft Commerce to ShipStation as a custom store: ShipStation pulls completed orders from your site, and pushes tracking numbers back.

## Where to go

**Setting it up?** [Installation](./installation.md) goes from `composer require` to a connected ShipStation store.

**Running the store day-to-day?** See the user guide:

- [Order export](./user-guide/order-export.md), which orders go out, what each one carries, and how multiple ShipStation stores work
- [Shipping notifications](./user-guide/shipping-notifications.md), what happens in Craft when an order ships
- [Troubleshooting](./user-guide/troubleshooting.md), orders missing from ShipStation, failed connections, missing tracking

**Building on top of it?**

- [Customizing exported orders](./dev-guide/customizing-exported-orders.md), rewrite any field before it is sent
- [XML fields](./reference/xml-fields.md), every field in the export and where its value comes from
- [Endpoint](./reference/endpoint.md), parameters, responses, and errors
- [Events](./reference/events.md), what fires, what is on the payload, how to listen
