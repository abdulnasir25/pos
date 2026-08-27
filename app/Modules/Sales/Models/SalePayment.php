<?php

namespace App\Modules\Sales\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

class SalePayment extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = ['sale_id', 'payment_method_id', 'amount', 'paid_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }
}
