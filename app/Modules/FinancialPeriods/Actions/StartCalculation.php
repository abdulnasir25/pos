<?php

namespace App\Modules\FinancialPeriods\Actions;

use App\Modules\FinancialPeriods\Enums\FinancialPeriodStatus;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;

/**
 * OPEN → CALCULATING. Purely a lifecycle boundary — sets calculated_at
 * and nothing else. Does not compute profit, commission, or touch
 * sales/inventory/customers in any way; those are later modules' jobs.
 */
class StartCalculation
{
    public function __construct(private readonly ApplyPeriodTransition $transition) {}

    public function handle(FinancialPeriod $period): FinancialPeriod
    {
        return $this->transition->handle($period, FinancialPeriodStatus::Calculating, [
            'calculated_at' => now(),
        ]);
    }
}
