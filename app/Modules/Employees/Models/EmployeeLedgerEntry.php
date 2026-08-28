<?php

namespace App\Modules\Employees\Models;

use App\Modules\Employees\Enums\EmployeeLedgerEntryType;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only running statement per employee. No update or delete route
 * exists anywhere in this module.
 */
class EmployeeLedgerEntry extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'employee_id', 'entry_type', 'amount', 'financial_period_id', 'reference_type', 'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => EmployeeLedgerEntryType::class,
            'amount' => 'decimal:2',
        ];
    }
}
