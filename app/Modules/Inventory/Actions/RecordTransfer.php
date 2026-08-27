<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\MovementReason;
use App\Modules\Inventory\Support\UnitConversionService;
use App\Modules\Products\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Two movements — transfer_out at the source, transfer_in at the
 * destination — that must succeed or fail together. Wrapped in its own
 * transaction on top of PostInventoryMovement's per-call transaction:
 * SQLite (and every other supported driver) treats a transaction opened
 * while already inside one as a savepoint, so if transfer_in fails after
 * transfer_out already succeeded, both roll back — never a stock unit
 * that vanished from the source without appearing at the destination.
 */
class RecordTransfer
{
    public function __construct(
        private readonly PostInventoryMovement $post,
        private readonly UnitConversionService $units,
    ) {}

    public function handle(
        Product $product,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $unitId,
        string $quantity,
        ?int $actorId = null,
    ): array {
        $baseQuantity = $this->units->toBaseUnit($product, $unitId, $quantity);

        return DB::connection(config('tenancy.tenant_connection', 'tenant'))->transaction(function () use (
            $product, $fromWarehouseId, $toWarehouseId, $baseQuantity, $actorId,
        ) {
            $out = $this->post->handle(
                productId: $product->id,
                warehouseId: $fromWarehouseId,
                reason: MovementReason::TransferOut,
                quantityBaseUnit: $baseQuantity,
                actorId: $actorId,
            );

            $in = $this->post->handle(
                productId: $product->id,
                warehouseId: $toWarehouseId,
                reason: MovementReason::TransferIn,
                quantityBaseUnit: $baseQuantity,
                actorId: $actorId,
            );

            return ['out' => $out, 'in' => $in];
        });
    }
}
