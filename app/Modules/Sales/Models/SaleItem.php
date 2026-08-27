<?php

namespace App\Modules\Sales\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'sale_id', 'product_id', 'unit_id', 'quantity', 'unit_price',
        'discount', 'unit_cost_snapshot', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount' => 'decimal:2',
            'unit_cost_snapshot' => 'decimal:4',
            'line_total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
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
