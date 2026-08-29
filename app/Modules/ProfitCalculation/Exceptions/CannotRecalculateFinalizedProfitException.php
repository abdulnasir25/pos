<?php

namespace App\Modules\ProfitCalculation\Exceptions;

use RuntimeException;

class CannotRecalculateFinalizedProfitException extends RuntimeException
{
    public static function forPeriod(int $financialPeriodId): self
    {
        return new self("Profit calculation for financial period #{$financialPeriodId} is already finalized and cannot be recalculated.");
    }
}
