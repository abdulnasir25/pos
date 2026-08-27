<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\MovementReason;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Support\UnitConversionService;
use App\Modules\Products\Models\Product;

/**
 * Only for a return accepted back into sellable stock. A return in
 * damaged/unsellable condition should be recorded via RecordDamage
 * instead — see the Phase 1 requirements' flagged gap on this exact
 * question, still open. This action assumes the "sellable" branch of
 * that decision.
 */
class RecordSaleReturn
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
            reason: MovementReason::SaleReturn,
            quantityBaseUnit: $baseQuantity,
            referenceType: $referenceType,
            referenceId: $referenceId,
            actorId: $actorId,
        );
    }
}
