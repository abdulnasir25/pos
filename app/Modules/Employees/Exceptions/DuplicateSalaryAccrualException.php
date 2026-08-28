<?php

namespace App\Modules\Employees\Exceptions;

use RuntimeException;

class DuplicateSalaryAccrualException extends RuntimeException
{
    public static function forEmployeeAndPeriod(int $employeeId, int $financialPeriodId): self
    {
        return new self("Employee [{$employeeId}] already has a salary accrual recorded for financial period [{$financialPeriodId}].");
    }
}
