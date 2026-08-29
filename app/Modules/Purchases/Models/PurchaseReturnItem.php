<?php

namespace App\Modules\Purchases\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = ['purchase_return_id', 'purchase_item_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }
}
