<?php

namespace App\Modules\Employees\Actions;

use App\Modules\Employees\Enums\EmployeeLedgerEntryType;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\EmployeeLedgerEntry;
use App\Modules\Employees\Models\SalaryPayment;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Records that salary was actually PAID — the payout event plus its
 * matching employee_ledger_entries row, atomically. Does not require a
 * prior RecordSalaryAccrual call; this task's foundation keeps "owed"
 * and "paid" as independent facts rather than one gating the other.
 */
class RecordSalaryPayment
{
    public function handle(
        Employee $employee,
        FinancialPeriod $financialPeriod,
        string $amount,
        int $paymentMethodId,
        int $actorId,
    ): SalaryPayment {
        return DB::connection(config('tenancy.tenant_connection', 'tenant'))->transaction(function () use (
            $employee, $financialPeriod, $amount, $paymentMethodId, $actorId,
        ) {
            $payment = SalaryPayment::create([
                'employee_id' => $employee->id,
                'financial_period_id' => $financialPeriod->id,
                'amount' => $amount,
                'payment_method_id' => $paymentMethodId,
                'paid_at' => now(),
                'created_by' => $actorId,
            ]);

            EmployeeLedgerEntry::create([
                'employee_id' => $employee->id,
                'entry_type' => EmployeeLedgerEntryType::SalaryPayment,
                'amount' => $amount,
                'financial_period_id' => $financialPeriod->id,
                'reference_type' => SalaryPayment::class,
                'reference_id' => $payment->id,
            ]);

            return $payment;
        });
    }
}
