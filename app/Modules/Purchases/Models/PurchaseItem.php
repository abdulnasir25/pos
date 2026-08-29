<?php

namespace App\Modules\Purchases\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseItem extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'purchase_id', 'product_id', 'unit_id', 'quantity', 'unit_cost', 'discount', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'discount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function quantityReturned(): string
    {
        return (string) ($this->returnItems()->sum('quantity') ?: '0.0000');
    }

    public function quantityEligibleForReturn(): string
    {
        return bcsub((string) $this->quantity, $this->quantityReturned(), 4);
    }
}
