<?php

namespace App\Modules\Sales\Models;

use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Immutable once confirmed — nothing in this module updates a Sale's
 * totals in place. Cancellation and returns change `status` and write
 * new rows (SaleReturn, reversing InventoryMovements); they never
 * rewrite subtotal/total/paid_total after the fact.
 */
class Sale extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'customer_id', 'warehouse_id', 'cashier_id', 'sales_employee_id',
        'reference_no', 'status', 'subtotal', 'discount_total', 'total',
        'paid_total', 'balance_due', 'notes', 'confirmed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function isWalkIn(): bool
    {
        return $this->customer_id === null;
    }
}
