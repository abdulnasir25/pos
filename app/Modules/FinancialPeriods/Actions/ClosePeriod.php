<?php

namespace App\Modules\FinancialPeriods\Actions;

use App\Modules\FinancialPeriods\Enums\FinancialPeriodStatus;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;

/**
 * UNDER_REVIEW → CLOSED. Terminal — FinancialPeriodStatus::Closed has no
 * outgoing transitions at all, so ApplyPeriodTransition rejects every
 * subsequent call against a closed period, including another close.
 * There is no reopen path in this module.
 */
class ClosePeriod
{
    public function __construct(private readonly ApplyPeriodTransition $transition) {}

    public function handle(FinancialPeriod $period): FinancialPeriod
    {
        return $this->transition->handle($period, FinancialPeriodStatus::Closed, [
            'closed_at' => now(),
        ]);
    }
}
