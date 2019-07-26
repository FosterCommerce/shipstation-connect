<?php
namespace fostercommerce\shipstationconnect\web\twig\filters;

use Craft;
use craft\base\Field;
use craft\fields\Matrix;

class IsMatrixFilter extends \Twig_Extension
{
    public function getName()
    {
        return 'IsMatrixFilter';
    }

    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('is_matrix', [$this, 'is_matrix']),
        ];
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('is_matrix', [$this, 'is_matrix']),
        ];
    }

    public function is_matrix(Field $field)
    {
        return $field instanceof Matrix;
    }
}

