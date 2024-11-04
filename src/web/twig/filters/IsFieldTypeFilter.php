<?php

namespace fostercommerce\shipstationconnect\web\twig\filters;

use craft\base\Field;
use craft\fields\Assets;
use craft\fields\Dropdown;
use craft\fields\Matrix;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class IsFieldTypeFilter extends AbstractExtension
{
	public function getName(): string
	{
		return 'IsMatrixFilter';
	}

	public function getFilters(): array
	{
		return [
			new TwigFilter('is_matrix', $this->isMatrixField(...)),
			new TwigFilter('is_dropdown', $this->isDropdownField(...)),
			new TwigFilter('is_asset', $this->isAssetField(...)),
		];
	}

	public function getFunctions(): array
	{
		return [
			new TwigFunction('is_matrix', $this->isMatrixField(...)),
			new TwigFunction('is_dropdown', $this->isDropdownField(...)),
			new TwigFunction('is_asset', $this->isAssetField(...)),
		];
	}

	public function isMatrixField(Field $field): bool
	{
		return $field instanceof Matrix;
	}

	public function isDropdownField(Field $field): bool
	{
		return $field instanceof Dropdown;
	}

	public function isAssetField(Field $field): bool
	{
		return $field instanceof Assets;
	}
}
