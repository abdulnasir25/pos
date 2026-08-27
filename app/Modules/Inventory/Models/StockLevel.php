<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

/**
 * A derived, reconcilable cache (see RecalculateStockLevel) — not the
 * source of truth for history, InventoryMovement is. This table exists
 * primarily as the concurrency-control primitive for stock-out; see the
 * Inventory module's race-condition analysis for why.
 */
class StockLevel extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = ['product_id', 'warehouse_id', 'quantity_base_unit', 'average_cost'];

    protected function casts(): array
    {
        return [
            'quantity_base_unit' => 'decimal:4',
            'average_cost' => 'decimal:4',
        ];
    }
}
