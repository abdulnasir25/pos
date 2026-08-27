<?php

namespace App\Modules\Inventory\Exceptions;

use InvalidArgumentException;

class InvalidUnitConversionException extends InvalidArgumentException
{
    public static function noConversionDefined(int $productId, int $unitId): self
    {
        return new self(
            "Product [{$productId}] has no unit conversion defined for unit [{$unitId}], "
            ."and that unit is not the product's base unit."
        );
    }
}
