<?php

namespace fostercommerce\shipstationconnect\models;

use craft\base\Model;

class Settings extends Model
{
	/**
	 * @var int
	 */
	public const DEFAULT_PAGE_SIZE = 25;

	public string $storesFieldHandle = '';

	public string $shipstationUsername = '';

	public string $shipstationPassword = '';

	public int $ordersPageSize = self::DEFAULT_PAGE_SIZE;

	public string $orderIdPrefix = '';

	public bool $failOnValidation = false;

	public ?string $productImagesHandle = null;

	public string $shippedStatusHandle = 'shipped';

	public string $matrixFieldHandle = 'shippingInfo';

	public string $entryTypeHandle = 'shippingInfo';

	public string $carrierFieldHandle = 'carrier';

	public string $serviceFieldHandle = 'service';

	public string $trackingNumberFieldHandle = 'trackingNumber';

	public bool $billingSameAsShipping = false;

	public string $phoneNumberFieldHandle = '';

	/**
	 * Utility to help filter out fields we don't want to list on the settings page.
	 *
	 * @param class-string $className
	 */
	public static function isA(string $className, mixed $target): bool
	{
		return $target instanceof $className;
	}
}
