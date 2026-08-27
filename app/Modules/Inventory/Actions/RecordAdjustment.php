<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\MovementReason;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Support\UnitConversionService;
use App\Modules\Products\Models\Product;

/**
 * A manual stock-count correction, signed either direction. Never
 * hard-coded to only increase or only decrease — that's exactly the kind
 * of business assumption the architecture rules against. A negative
 * adjustment still respects the same "can't go below zero" guard as
 * every other decrease.
 */
class RecordAdjustment
{
    public function __construct(
        private readonly PostInventoryMovement $post,
        private readonly UnitConversionService $units,
    ) {}

    /**
     * @param  string  $signedQuantity  e.g. "-3.0000" for a shrinkage correction, "3.0000" for a found-stock correction.
     */
    public function handle(
        Product $product,
        int $warehouseId,
        int $unitId,
        string $signedQuantity,
        ?int $actorId = null,
    ): InventoryMovement {
        $baseQuantity = $this->units->toBaseUnit($product, $unitId, $signedQuantity);

        return $this->post->handle(
            productId: $product->id,
            warehouseId: $warehouseId,
            reason: MovementReason::Adjustment,
            quantityBaseUnit: $baseQuantity,
            actorId: $actorId,
        );
    }
}
