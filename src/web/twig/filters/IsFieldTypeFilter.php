<?php
namespace fostercommerce\shipstationconnect\web\twig\filters;

use Craft;
use craft\base\Field;
use craft\fields\Matrix;
use craft\fields\Dropdown;

class IsFieldTypeFilter extends \Twig_Extension
{
    public function getName()
    {
        return 'IsMatrixFilter';
    }

    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('is_matrix', [$this, 'is_matrix']),
            new \Twig_SimpleFilter('is_dropdown', [$this, 'is_dropdown']),
        ];
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('is_matrix', [$this, 'is_matrix']),
            new \Twig_SimpleFunction('is_dropdown', [$this, 'is_dropdown']),
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

