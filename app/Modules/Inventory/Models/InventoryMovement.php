<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\MovementReason;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only. No update or delete route exists anywhere in this module —
 * a correction is always a new, opposite-signed movement.
 */
class InventoryMovement extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity_base_unit',
        'unit_cost',
        'reason',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_base_unit' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'reason' => MovementReason::class,
        ];
    }
}
