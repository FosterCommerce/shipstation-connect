<?php

return [
	// Shipments provider
	'provider.displayName' => 'ShipStation',
	'provider.shipnotifyWebhookMessage' => 'ShipStation “shipnotify” webhook',

	// Shipments provider settings: custom store URL
	'settings.customXmlPageUrl.label' => 'Custom XML Page URL',
	'settings.customXmlPageUrl.instructions' => 'When setting up your ShipStation Custom Store, enter this URL so ShipStation can retrieve and update orders for this integration.',
	'settings.customXmlPageUrl.unsavedNotice' => 'Save the integration to see the URL.',

	// Shipments provider settings: credentials
	'settings.username.label' => 'Username',
	'settings.username.instructions' => 'HTTP basic auth username ShipStation will send. Leave blank to fall back to the global ShipStation Connect plugin setting.',
	'settings.password.label' => 'Password',
	'settings.password.instructions' => 'HTTP basic auth password ShipStation will send. Leave blank to fall back to the global ShipStation Connect plugin setting.',

	// Shipments provider settings: export + transitions
	'settings.exportFilter.label' => 'Export filter',
	'settings.exportFilter.instructions' => 'Restrict the export to shipments at this fulfillment status.',
	'settings.exportFilter.noFilter' => 'No filter',
	'settings.dontTransition' => 'Don’t transition',
	'settings.shippedFulfillment.label' => 'Shipnotify fulfillment transition',
	'settings.shippedFulfillment.instructions' => 'Fulfillment status applied when ShipStation reports a shipment as shipped.',
	'settings.shippedShipping.label' => 'Shipnotify shipping transition',
	'settings.shippedShipping.instructions' => 'Optional shipping-axis status applied alongside the fulfillment transition.',

	// Shipments provider settings: per-shipment money toggles
	'settings.sendTax.label' => 'Send tax to ShipStation',
	'settings.sendTax.instructions' => 'Split the parent order’s tax pro-rata across its shipments and send the per-shipment portion. Off by default; tax is a parent-order figure and most stores prefer ShipStation to show $0 tax per shipment.',
	'settings.sendShipping.label' => 'Send shipping cost to ShipStation',
	'settings.sendShipping.instructions' => 'Split the parent order’s shipping cost pro-rata across its shipments and send the per-shipment portion. Off by default; ShipStation usually pulls real carrier cost from the rate it purchases.',
	'settings.sendDiscount.label' => 'Send discount to ShipStation',
	'settings.sendDiscount.instructions' => 'Split the parent order’s discount pro-rata across its shipments and send the per-shipment portion as a coupon adjustment line item. Off by default; discount is a parent-order figure.',
];
