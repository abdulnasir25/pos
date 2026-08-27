<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\StockLevel;

class StockLevelService
{
    /**
     * The fast path — reads the maintained cache.
     */
    public function currentStock(int $productId, int $warehouseId): string
    {
        return (string) (StockLevel::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity_base_unit') ?? '0.0000');
    }

    /**
     * The source-of-truth path — sums the immutable ledger directly,
     * ignoring the cache entirely. Used by tests and by
     * RecalculateStockLevel to verify (or restore) that the cache
     * actually agrees with history.
     */
    public function currentStockFromLedger(int $productId, int $warehouseId): string
    {
        $sum = InventoryMovement::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity_base_unit');

        return bcadd('0', (string) $sum, 4);
    }
}
