<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Exceptions\InvalidUnitConversionException;
use App\Modules\Products\Models\Product;

/**
 * Converts a quantity expressed in an arbitrary sellable/purchasable unit
 * into the product's base unit — the only unit inventory_movements and
 * stock_levels ever store a quantity in. Every Inventory action calls
 * this before posting a movement; nowhere else in the codebase should
 * duplicate this arithmetic.
 */
class UnitConversionService
{
    public function toBaseUnit(Product $product, int $unitId, string $quantity): string
    {
        if ($unitId === $product->base_unit_id) {
            return $quantity;
        }

        $conversion = $product->unitConversions()->where('unit_id', $unitId)->first();

        if ($conversion === null) {
            throw InvalidUnitConversionException::noConversionDefined($product->id, $unitId);
        }

        return bcmul($quantity, (string) $conversion->factor, 4);
    }
}
