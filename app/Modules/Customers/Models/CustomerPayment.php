<?php

namespace App\Modules\Customers\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

class CustomerPayment extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = ['customer_id', 'payment_method_id', 'amount', 'paid_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }
}
