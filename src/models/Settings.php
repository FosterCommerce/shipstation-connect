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

	public ?string $productImagesHandle = null;

	public string $shippedStatusHandle = 'shipped';

	public string $matrixFieldHandle = 'shippingInfo';

	public string $blockTypeHandle = 'shippingInfo';

	public string $carrierFieldHandle = 'carrier';

	public string $serviceFieldHandle = 'service';

	public string $trackingNumberFieldHandle = 'trackingNumber';

	public bool $billingSameAsShipping = false;

	public string $phoneNumberFieldHandle = '';
}
