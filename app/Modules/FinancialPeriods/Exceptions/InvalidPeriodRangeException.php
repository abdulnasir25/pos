<?php

namespace App\Modules\FinancialPeriods\Exceptions;

use InvalidArgumentException;

class InvalidPeriodRangeException extends InvalidArgumentException
{
    public static function endBeforeStart(string $start, string $end): self
    {
        return new self("period_end [{$end}] cannot be before period_start [{$start}].");
    }
}
