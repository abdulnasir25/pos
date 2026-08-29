<?php

namespace App\Modules\Commission\Exceptions;

use RuntimeException;

class CommissionAlreadyCalculatedException extends RuntimeException
{
    public static function forPeriod(int $financialPeriodId): self
    {
        return new self("Commission has already been calculated for financial period #{$financialPeriodId}.");
    }
}
