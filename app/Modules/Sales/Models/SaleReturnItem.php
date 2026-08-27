<?php

namespace App\Modules\Sales\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

class SaleReturnItem extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = ['sale_return_id', 'sale_item_id', 'quantity', 'condition'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }
}
