<?php

namespace App\Modules\Employees\Models;

use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The compensation-subject entity. Never the same row as a User — see
 * the module's Actions for the only supported ways to link/unlink one.
 * No delete route exists anywhere in this module; termination is
 * ChangeEmployeeStatus, never a row removal.
 */
class Employee extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'hired_at',
        'terminated_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'hired_at' => 'date',
            'terminated_at' => 'date',
            'status' => EmployeeStatus::class,
        ];
    }

    public function compensationHistory(): HasMany
    {
        return $this->hasMany(EmployeeCompensation::class);
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(EmployeeLedgerEntry::class);
    }
}
