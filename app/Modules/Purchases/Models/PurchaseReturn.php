<?php

namespace App\Modules\Purchases\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = ['purchase_id', 'processed_by', 'credit_amount', 'notes'];

    protected function casts(): array
    {
        return ['credit_amount' => 'decimal:2'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
