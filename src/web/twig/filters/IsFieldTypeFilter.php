<?php
namespace fostercommerce\shipstationconnect\web\twig\filters;

use Craft;
use craft\base\Field;
use craft\fields\Matrix;
use craft\fields\Dropdown;
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
            new TwigFilter('is_matrix', [$this, 'is_matrix']),
            new TwigFilter('is_dropdown', [$this, 'is_dropdown']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_matrix', [$this, 'is_matrix']),
            new TwigFunction('is_dropdown', [$this, 'is_dropdown']),
        ];
    }

    public function is_matrix(Field $field)
    {
        return $field instanceof Matrix;
    }

    public function is_dropdown(Field $field)
    {
        return $field instanceof Dropdown;
    }
}

