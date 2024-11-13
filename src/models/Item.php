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
use JMS\Serializer\Annotation\Accessor;
use JMS\Serializer\Annotation\AccessType;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;
use yii\base\Exception;
use yii\base\InvalidConfigException;

#[AccessType([
	'type' => 'public_method',
])]
class Item extends Base
{
	#[Groups(['export'])]
	#[SerializedName('SKU')]
	#[Accessor([
		'getter' => 'getSku',
		'setter' => 'setSku',
	])]
	private string $sku;

	#[Groups(['export'])]
	#[SerializedName('Name')]
	#[Accessor([
		'getter' => 'getName',
		'setter' => 'setName',
	])]
	private string $name;

	#[Groups(['export'])]
	#[SerializedName('Weight')]
	#[Accessor([
		'getter' => 'getWeight',
		'setter' => 'setWeight',
	])]
	private float $weight = 0.0;

	#[Groups(['export'])]
	#[SerializedName('Quantity')]
	#[Accessor([
		'getter' => 'getQuantity',
		'setter' => 'setQuantity',
	])]
	private int $quantity;

	#[Groups(['export'])]
	#[SerializedName('UnitPrice')]
	#[Accessor([
		'getter' => 'getUnitPrice',
		'setter' => 'setUnitPrice',
	])]
	private float $unitPrice;

	#[Groups(['export'])]
	#[SerializedName('ImageUrl')]
	#[Accessor([
		'getter' => 'getImageUrl',
		'setter' => 'setImageUrl',
	])]
	private ?string $imageUrl = '';

	#[Groups(['export'])]
	#[SerializedName('WeightUnits')]
	#[Accessor([
		'getter' => 'getWeightUnits',
		'setter' => 'setWeightUnits',
	])]
	private string $weightUnits = '';

	#[Groups(['export'])]
	#[SerializedName('Adjustment')]
	#[Accessor([
		'getter' => 'isAdjustment',
		'setter' => 'setAdjustment',
	])]
	private bool $adjustment = false;

	/**
	 * @var Option[]
	 */
	#[Groups(['export'])]
	#[SerializedName('Options')]
	#[Accessor([
		'getter' => 'getOptions',
		'setter' => 'setOptions',
	])]
	private array $options = [];

	public function getSku(): string
	{
		return $this->limitString($this->sku, static::STRING_100);
	}

	public function setSku(string $sku): void
	{
		$this->sku = $sku;
	}

	public function getName(): string
	{
		return $this->limitString($this->name, static::STRING_200);
	}

	public function setName(string $name): void
	{
		$this->name = $name;
	}

	public function getWeight(): float
	{
		return $this->weight;
	}

	public function setWeight(float $weight): void
	{
		$this->weight = $weight;
	}

	public function getQuantity(): int
	{
		return $this->quantity;
	}

	public function setQuantity(int $quantity): void
	{
		$this->quantity = $quantity;
	}

	public function getUnitPrice(): float
	{
		return $this->unitPrice;
	}

	public function setUnitPrice(float $unitPrice): void
	{
		$this->unitPrice = $unitPrice;
	}

	public function getImageUrl(): ?string
	{
		return $this->imageUrl;
	}

	public function setImageUrl(?string $imageUrl): void
	{
		$this->imageUrl = $imageUrl;
	}

	public function getWeightUnits(): string
	{
		return $this->weightUnits;
	}

	public function setWeightUnits(string $weightUnits): void
	{
		$this->weightUnits = $weightUnits;
	}

	public function isAdjustment(): bool
	{
		return $this->adjustment;
	}

	public function setAdjustment(bool $adjustment): void
	{
		$this->adjustment = $adjustment;
	}

	/**
	 * @return Option[]
	 */
	public function getOptions(): array
	{
		return $this->options;
	}

	/**
	 * @param Option[] $options
	 */
	public function setOptions(array $options): void
	{
		$this->options = $options;
	}

	public static function asAdjustment(string $name, float $totalDiscount): self
	{
		return new self([
			'sku' => '',
			'name' => trim($name),
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
			'lb' => ['Pounds', round($lineItem->weight, 2)],
			default => ['Grams', round($lineItem->weight * 1000, 2)],
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
				->map(static fn ($value, $key): Option => new Option([
					'name' => $key,
					'value' => $value,
				]))
				->toArray(),
		]);
	}
}
