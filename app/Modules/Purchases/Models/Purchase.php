<?php

namespace App\Modules\Purchases\Models;

use App\Modules\Purchases\Enums\PurchaseStatus;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Immutable once confirmed — nothing in this module updates a
 * Purchase's totals in place. Cancellation and returns change `status`
 * and write new rows; they never rewrite subtotal/total/paid_total
 * after the fact. Mirrors Sale exactly, opposite direction.
 */
class Purchase extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'supplier_id', 'warehouse_id', 'employee_id',
        'reference_no', 'status', 'subtotal', 'discount_total', 'total',
        'paid_total', 'balance_payable', 'confirmed_at', 'cancelled_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'balance_payable' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }
}
