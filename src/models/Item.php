<?php

namespace fostercommerce\shipstationconnect\models;

use craft\commerce\elements\Variant;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin as CommercePlugin;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use fostercommerce\shipstationconnect\Plugin;
use Symfony\Component\Serializer\Annotation\SerializedName;
use yii\base\Exception;
use yii\base\InvalidConfigException;

class Item extends Base
{
	#[SerializedName('SKU')]
	public string $sku;

	#[SerializedName('Name')]
	public string $name;

	#[SerializedName('Weight')]
	public float $weight;

	#[SerializedName('Quantity')]
	public int $quantity;

	#[SerializedName('UnitPrice')]
	public float $unitPrice;

	#[SerializedName('ImageUrl')]
	public string $imageUrl = '';

	#[SerializedName('WeightUnits')]
	public string $weightUnits = '';

	#[SerializedName('Adjustment')]
	public bool $adjustment = false;

	/**
	 * @var array<string, string>
	 */
	#[SerializedName('Options')]
	public array $options = [];

	public static function asAdjustment(float $totalDiscount): self
	{
		return new self([
			'sku' => '',
			'name' => 'couponCode',
			'quantity' => 1,
			'unitPrice' => round($totalDiscount, 2),
			'adjustment' => true,
		]);
	}

	/**
	 * @throws Exception
	 * @throws InvalidConfigException
	 */
	public static function fromCommerceLineItem(LineItem $lineItem): self
	{
		/** @var array{sku: string} $snapshot */
		$snapshot = Json::decodeIfJson($lineItem->snapshot);

		/** @var string $weightUnits */
		/** @var float $weight */
		[$weightUnits, $weight] = match (CommercePlugin::getInstance()?->settings->weightUnits) {
			// kilograms need to be converted to grams for ShipStation
			'kg' => ['Grams', round($lineItem->weight * 1000, 2)],
			default => ['Pounds', round($lineItem->weight, 2)],
		};

		$imageUrl = null;
		$productImagesHandle = Plugin::getInstance()?->settings->productImagesHandle;
		/** @var ?Variant $purchasable */
		$purchasable = $lineItem->getPurchasable();
		if ($productImagesHandle !== null && $purchasable !== null) {
			/** @var ?AssetQuery<int, Asset> $assetQuery */
			$assetQuery = $purchasable->{$productImagesHandle};
			if ($assetQuery === null) {
				// Fallback to the product if the variant does not have an asset
				/** @var ?AssetQuery<int, Asset> $assetQuery */
				$assetQuery = $purchasable->product->{$productImagesHandle};
			}
			if ($assetQuery !== null) {
				/** @var ?Asset $asset */
				$asset = $assetQuery->one();
				$assetUrl = $asset?->getUrl();
				if ($assetUrl !== null) {
					$imageUrl = UrlHelper::siteUrl($assetUrl);
				}
			}
		}

		return new self([
			'sku' => $snapshot['sku'],
			'name' => substr($lineItem->getDescription(), 0, 200),
			'weight' => $weight,
			'weightUnits' => $weightUnits,
			'quantity' => $lineItem->qty,
			'unitPrice' => round($lineItem->salePrice, 2),
			'imageUrl' => $imageUrl,
			'options' => collect($lineItem->options)
				->map(static fn ($value, $key) => new Option([
					'name' => $key,
					'value' => $value,
				]))
				->toArray(),
		]);
	}
}
