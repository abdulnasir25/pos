<?php

namespace App\Modules\Employees\Actions;

use App\Modules\Employees\Enums\EmployeeLedgerEntryType;
use App\Modules\Employees\Exceptions\DuplicateSalaryAccrualException;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\EmployeeLedgerEntry;
use App\Modules\Employees\Support\ResolveSalaryForDate;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Records that salary was OWED for a period — deliberately separate
 * from RecordSalaryPayment, which records what was actually PAID.
 * Never called automatically by anything (not by Financial Period's
 * lifecycle, not by Employee creation) — see this module's design
 * notes for why: nothing in the approved schema requires automatic
 * accrual, and inventing it here would mean Employee reaching into
 * Financial Period's lifecycle from the wrong direction.
 *
 * Resolves the owed amount from the employee's compensation as of the
 * period's start date, unless a caller supplies an explicit override.
 */
class RecordSalaryAccrual
{
    public function __construct(private readonly ResolveSalaryForDate $resolveSalary) {}

    public function handle(Employee $employee, FinancialPeriod $financialPeriod, ?string $amount = null): EmployeeLedgerEntry
    {
        $alreadyAccrued = EmployeeLedgerEntry::where('employee_id', $employee->id)
            ->where('financial_period_id', $financialPeriod->id)
            ->where('entry_type', EmployeeLedgerEntryType::SalaryAccrual)
            ->exists();

        if ($alreadyAccrued) {
            throw DuplicateSalaryAccrualException::forEmployeeAndPeriod($employee->id, $financialPeriod->id);
        }

        $owed = $amount ?? $this->resolveSalary->handle($employee, $financialPeriod->period_start->toDateString());

        return DB::connection(config('tenancy.tenant_connection', 'tenant'))->transaction(
            fn () => EmployeeLedgerEntry::create([
                'employee_id' => $employee->id,
                'entry_type' => EmployeeLedgerEntryType::SalaryAccrual,
                'amount' => $owed,
                'financial_period_id' => $financialPeriod->id,
            ])
        );
    }
}
