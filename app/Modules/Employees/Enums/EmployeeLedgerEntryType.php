<?php

namespace App\Modules\Employees\Enums;

/**
 * Only the two salary-related entry types are implemented this task.
 * commission_accrual / commission_payment / commission_correction are
 * named in the approved schema but deliberately not added here — the
 * underlying column is a plain string precisely so the Commission
 * module can extend this list later without a migration.
 */
enum EmployeeLedgerEntryType: string
{
    case SalaryAccrual = 'salary_accrual';
    case SalaryPayment = 'salary_payment';
}
