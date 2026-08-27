<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\MovementReason;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Support\UnitConversionService;
use App\Modules\Products\Models\Product;

/**
 * Called by the future Sales module when a sale is confirmed. Throws
 * InsufficientStockException if the warehouse doesn't have enough —
 * callers must catch this and fail the whole sale confirmation, since a
 * sale can never be partially stocked-out.
 */
class RecordSaleStockOut
{
    public function __construct(
        private readonly PostInventoryMovement $post,
        private readonly UnitConversionService $units,
    ) {}

    public function handle(
        Product $product,
        int $warehouseId,
        int $unitId,
        string $quantity,
        string $referenceType,
        int $referenceId,
        ?int $actorId = null,
    ): InventoryMovement {
        $baseQuantity = $this->units->toBaseUnit($product, $unitId, $quantity);

        return $this->post->handle(
            productId: $product->id,
            warehouseId: $warehouseId,
            reason: MovementReason::Sale,
            quantityBaseUnit: $baseQuantity,
            referenceType: $referenceType,
            referenceId: $referenceId,
            actorId: $actorId,
        );
    }
}
