<?php

namespace App\Modules\Reports\Support;

use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use App\Modules\ProfitCalculation\Models\ProfitCalculation;
use App\Modules\Reports\DTOs\ProfitAndLossReport;
use App\Modules\Reports\Exceptions\ProfitCalculationNotFoundException;

/**
 * A read-only presentation of ProfitCalculation's own numbers —
 * deliberately does not recompute anything, so a P&L report can never
 * disagree with the actual profit_calculations row it's reporting on.
 */
class ProfitAndLossReportBuilder
{
    public function build(FinancialPeriod $period): ProfitAndLossReport
    {
        $calculation = ProfitCalculation::where('financial_period_id', $period->id)->first();

        if ($calculation === null) {
            throw ProfitCalculationNotFoundException::forPeriod($period->id);
        }

        return new ProfitAndLossReport(
            periodStart: $period->period_start->toDateString(),
            periodEnd: $period->period_end->toDateString(),
            revenue: (string) $calculation->revenue,
            cogs: (string) $calculation->cogs,
            grossProfit: (string) $calculation->gross_profit,
            salaryExpense: (string) $calculation->salary_expense,
            commissionExpense: (string) $calculation->commission_expense,
            otherOperatingExpenses: (string) $calculation->other_operating_expenses,
            netProfit: (string) $calculation->net_profit,
            distributableProfit: (string) $calculation->distributable_profit,
            retainedProfit: (string) $calculation->retained_profit,
            status: $calculation->status->value,
        );
    }
}
