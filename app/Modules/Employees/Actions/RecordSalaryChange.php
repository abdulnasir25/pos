<?php

namespace App\Modules\Employees\Actions;

use App\Modules\Employees\Exceptions\InvalidCompensationRangeException;
use App\Modules\Employees\Exceptions\OverlappingCompensationException;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\EmployeeCompensation;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The only supported way to record a salary — first hire or a change.
 * monthly_salary on an existing row is never rewritten by this class.
 *
 * The one row-level mutation this Action performs: if the new record
 * starts strictly after an existing record whose effective_to is still
 * null (the "currently open" one), that record's effective_to is set to
 * one day before the new record's start — this is the normal "the raise
 * supersedes the previous salary" case, and it's a boundary adjustment,
 * not a rewrite of what that employee was actually paid during that
 * span. Any other overlap (a genuinely conflicting range against an
 * already-closed historical record, or a duplicate start date) is
 * rejected outright — this class never auto-closes more than the one
 * record actually being superseded.
 *
 * Uses the same BEGIN IMMEDIATE pattern as FinancialPeriods'
 * CreateFinancialPeriod, for the identical reason: the overlap check
 * and the writes below must not be separated by another writer, which a
 * plain DB::transaction() does not guarantee on SQLite.
 */
class RecordSalaryChange
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Employee $employee, string $monthlySalary, string $effectiveFrom, ?string $effectiveTo = null): EmployeeCompensation
    {
        $this->tenant->get();

        if ($effectiveTo !== null && $effectiveTo < $effectiveFrom) {
            throw InvalidCompensationRangeException::endBeforeStart($effectiveFrom, $effectiveTo);
        }

        $connection = DB::connection(config('tenancy.tenant_connection', 'tenant'));
        $pdo = $connection->getPdo();

        $pdo->exec('BEGIN IMMEDIATE');

        try {
            $existing = $connection->table('employee_compensation')
                ->where('employee_id', $employee->id)
                ->get(['id', 'effective_from', 'effective_to']);

            $upperBound = $effectiveTo ?? '9999-12-31';
            $recordToClose = null;

            foreach ($existing as $record) {
                // Normalize to date-only before comparing: the column is
                // cast 'date' for reading, but Laravel's query grammar
                // persists it as a full "Y-m-d H:i:s" string regardless
                // — comparing that raw against a plain "Y-m-d" input
                // breaks exactly on same-day boundaries, since the
                // datetime string sorts after the bare date it should
                // tie with.
                $recordLower = substr($record->effective_from, 0, 10);
                $recordUpper = $record->effective_to !== null ? substr($record->effective_to, 0, 10) : '9999-12-31';

                $overlaps = $recordLower <= $upperBound && $recordUpper >= $effectiveFrom;

                if (! $overlaps) {
                    continue;
                }

                // The one shape of overlap that's actually valid: this
                // record is still open-ended and started before the new
                // one — that's the current salary being superseded.
                if ($record->effective_to === null && $recordLower < $effectiveFrom) {
                    $recordToClose = $record;

                    continue;
                }

                throw OverlappingCompensationException::forEmployee($employee->id, $effectiveFrom);
            }

            if ($recordToClose !== null) {
                $connection->table('employee_compensation')
                    ->where('id', $recordToClose->id)
                    ->update([
                        'effective_to' => date('Y-m-d', strtotime($effectiveFrom.' -1 day')),
                        'updated_at' => now(),
                    ]);
            }

            $compensation = EmployeeCompensation::create([
                'employee_id' => $employee->id,
                'monthly_salary' => $monthlySalary,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
            ]);

            $pdo->exec('COMMIT');

            return $compensation;
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');

            throw $e;
        }
    }
}
