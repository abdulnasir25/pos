<?php

namespace App\Modules\Purchases\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

class PurchasePayment extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = ['purchase_id', 'payment_method_id', 'amount', 'paid_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }
}
