<?php

namespace App\Modules\Employees\Support;

use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\EmployeeCompensation;

/**
 * Answers "what was this employee's monthly salary on this date" by
 * reading the compensation history — never a cached/current-only value.
 * A raise recorded today does not change what this returns for a date
 * before that raise took effect.
 *
 * Compares via SQL DATE(...) rather than a plain string comparison —
 * Laravel's 'date' cast only truncates the value when *reading* it back
 * as a Carbon; what actually gets written to the column is the query
 * grammar's full datetime format ("2026-01-01 00:00:00"), not "2026-01-
 * 01". A plain string comparison against a date-only input breaks
 * exactly on same-day boundaries, since "2026-01-01 00:00:00" sorts
 * lexicographically after the bare "2026-01-01" it should be equal to.
 * DATE(...) normalizes both sides regardless of how the column was
 * actually persisted, and is portable across SQLite/MySQL/Postgres.
 */
class ResolveSalaryForDate
{
    public function handle(Employee $employee, string $date): ?string
    {
        $record = EmployeeCompensation::where('employee_id', $employee->id)
            ->whereRaw('DATE(effective_from) <= ?', [$date])
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')->orWhereRaw('DATE(effective_to) >= ?', [$date]);
            })
            ->orderByDesc('effective_from')
            ->first();

        return $record?->monthly_salary;
    }
}
