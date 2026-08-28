<?php

namespace App\Modules\FinancialPeriods\Exceptions;

use App\Modules\FinancialPeriods\Enums\FinancialPeriodStatus;
use RuntimeException;

class InvalidPeriodTransitionException extends RuntimeException
{
    public static function forAttempt(int $periodId, FinancialPeriodStatus $from, FinancialPeriodStatus $to): self
    {
        return new self(
            "Financial period [{$periodId}] cannot transition from [{$from->value}] to [{$to->value}]. "
            .'Valid transitions are open → calculating → under_review → closed, forward only.'
        );
    }
}
