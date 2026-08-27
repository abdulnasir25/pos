<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\MovementReason;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Support\UnitConversionService;
use App\Modules\Products\Models\Product;

/**
 * Stock sent back to a supplier — a decrease. Throws
 * InsufficientStockException if trying to return more than is on hand,
 * which is the correct behavior: you cannot return stock that was
 * already sold or otherwise moved out.
 */
class RecordPurchaseReturn
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
            reason: MovementReason::PurchaseReturn,
            quantityBaseUnit: $baseQuantity,
            referenceType: $referenceType,
            referenceId: $referenceId,
            actorId: $actorId,
        );
    }
}
