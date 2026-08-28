<?php

namespace App\Modules\FinancialPeriods\Enums;

enum FinancialPeriodStatus: string
{
    case Open = 'open';
    case Calculating = 'calculating';
    case UnderReview = 'under_review';
    case Closed = 'closed';

    /**
     * The only valid forward transitions. Anything not listed here —
     * including every attempt to move backward, or to skip a step — is
     * rejected by MoveFinancialPeriod. There is no reopen path; a future
     * correction/reopen feature is explicitly out of scope for this
     * module.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Open => $target === self::Calculating,
            self::Calculating => $target === self::UnderReview,
            self::UnderReview => $target === self::Closed,
            self::Closed => false,
        };
    }
}
