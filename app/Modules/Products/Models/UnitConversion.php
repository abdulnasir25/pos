<?php

namespace App\Modules\Products\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

class UnitConversion extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = ['product_id', 'unit_id', 'factor'];

    protected function casts(): array
    {
        return ['factor' => 'decimal:4'];
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
