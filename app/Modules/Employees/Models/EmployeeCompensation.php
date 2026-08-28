<?php

namespace App\Modules\Employees\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per salary. monthly_salary on an existing row is never
 * rewritten — the only field ever updated after creation is
 * effective_to, and only by RecordSalaryChange closing out the
 * previously-open record when a new one supersedes it.
 */
class EmployeeCompensation extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = ['employee_id', 'monthly_salary', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }
}
