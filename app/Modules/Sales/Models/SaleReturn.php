<?php

namespace App\Modules\Sales\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = ['sale_id', 'processed_by', 'refund_amount', 'notes'];

    protected function casts(): array
    {
        return ['refund_amount' => 'decimal:2'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
