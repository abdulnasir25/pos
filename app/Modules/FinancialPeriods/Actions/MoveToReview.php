<?php

namespace App\Modules\FinancialPeriods\Actions;

use App\Modules\FinancialPeriods\Enums\FinancialPeriodStatus;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;

/**
 * CALCULATING → UNDER_REVIEW. Records who moved the period into review
 * via reviewed_by — this marks "review started," not "period closed";
 * the period remains open to correction until ClosePeriod actually runs.
 */
class MoveToReview
{
    public function __construct(private readonly ApplyPeriodTransition $transition) {}

    public function handle(FinancialPeriod $period, int $reviewerId): FinancialPeriod
    {
        return $this->transition->handle($period, FinancialPeriodStatus::UnderReview, [
            'reviewed_by' => $reviewerId,
        ]);
    }
}
