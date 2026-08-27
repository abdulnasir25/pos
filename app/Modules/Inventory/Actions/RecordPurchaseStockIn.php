<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\MovementReason;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Support\UnitConversionService;
use App\Modules\Products\Models\Product;

/**
 * Called by the future Purchases module when a purchase is confirmed —
 * this module has no dependency in that direction. $referenceType/Id
 * point back at whatever purchase document caused the movement.
 */
class RecordPurchaseStockIn
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
        string $unitCost,
        string $referenceType,
        int $referenceId,
        ?int $actorId = null,
    ): InventoryMovement {
        $baseQuantity = $this->units->toBaseUnit($product, $unitId, $quantity);

        return $this->post->handle(
            productId: $product->id,
            warehouseId: $warehouseId,
            reason: MovementReason::Purchase,
            quantityBaseUnit: $baseQuantity,
            incomingUnitCost: $unitCost,
            referenceType: $referenceType,
            referenceId: $referenceId,
            actorId: $actorId,
        );
    }
}
