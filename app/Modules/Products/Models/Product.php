<?php

namespace App\Modules\Products\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = ['base_unit_id', 'name', 'sku', 'low_stock_threshold', 'status'];

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function unitConversions(): HasMany
    {
        return $this->hasMany(UnitConversion::class);
    }
}
