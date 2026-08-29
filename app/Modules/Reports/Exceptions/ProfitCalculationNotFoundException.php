<?php

namespace App\Modules\Reports\Exceptions;

use RuntimeException;

class ProfitCalculationNotFoundException extends RuntimeException
{
    public static function forPeriod(int $financialPeriodId): self
    {
        return new self(
            "No profit calculation exists yet for financial period #{$financialPeriodId} — ".
            'run CalculateProfitForPeriod first.'
        );
    }
}
