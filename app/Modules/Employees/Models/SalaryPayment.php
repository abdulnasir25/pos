<?php

namespace App\Modules\Employees\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable payout events — no update/delete route exists at the
 * application layer. A correction is a future financial transaction,
 * out of scope for this task.
 */
class SalaryPayment extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = ['employee_id', 'financial_period_id', 'amount', 'payment_method_id', 'paid_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }
}
