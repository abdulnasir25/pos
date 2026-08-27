<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\StockLevel;

/**
 * Rebuilds stock_levels.quantity_base_unit for one product/warehouse pair
 * from the immutable ledger — proof, and a recovery path, that the cache
 * never becomes the only record: it can always be thrown away and
 * regenerated from inventory_movements.
 *
 * Deliberately does not attempt to recompute average_cost retroactively.
 * Weighted-average cost is path-dependent (it depends on the *order*
 * purchases and consumptions happened in, not just their sum), so an
 * honest reconciliation of it would mean replaying the full movement
 * history in timestamp order — a heavier operation intentionally left
 * out of this phase rather than approximated.
 */
class RecalculateStockLevel
{
    public function handle(int $productId, int $warehouseId): StockLevel
    {
        $quantity = InventoryMovement::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity_base_unit');

        $stockLevel = StockLevel::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['quantity_base_unit' => 0, 'average_cost' => 0],
        );

        $stockLevel->update(['quantity_base_unit' => $quantity]);

        return $stockLevel;
    }
}
