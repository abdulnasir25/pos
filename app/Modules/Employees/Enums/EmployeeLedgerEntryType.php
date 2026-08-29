<?php

namespace App\Modules\Employees\Enums;

enum EmployeeLedgerEntryType: string
{
    case SalaryAccrual = 'salary_accrual';
    case SalaryPayment = 'salary_payment';
    case CommissionAccrual = 'commission_accrual';
    case CommissionPayment = 'commission_payment';
    case CommissionCorrection = 'commission_correction';
}
