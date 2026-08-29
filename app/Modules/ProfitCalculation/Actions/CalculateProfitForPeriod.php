<?php

namespace App\Modules\ProfitCalculation\Actions;

use App\Modules\Commission\Support\CalculatePeriodGrossProfit;
use App\Modules\Employees\Enums\EmployeeLedgerEntryType;
use App\Modules\Employees\Models\EmployeeLedgerEntry;
use App\Modules\Expenses\Support\SumExpensesForDateRange;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use App\Modules\ProfitCalculation\Enums\ProfitCalculationStatus;
use App\Modules\ProfitCalculation\Exceptions\CannotRecalculateFinalizedProfitException;
use App\Modules\ProfitCalculation\Exceptions\InvalidDistributableProfitException;
use App\Modules\ProfitCalculation\Models\ProfitCalculation;
use Illuminate\Support\Facades\DB;

/**
 * Combines every other financial module's own totals into one
 * snapshot — reads from their Support classes / the shared ledger,
 * never from their tables directly (see the model's docblock). Safe
 * to call more than once for the same period as long as it's still
 * Draft: each call overwrites the row with fresh numbers, which is the
 * normal review workflow (calculate, inspect, adjust
 * distributable_profit, recalculate) before FinalizeProfitCalculation
 * locks it.
 */
class CalculateProfitForPeriod
{
    public function __construct(
        private readonly CalculatePeriodGrossProfit $grossProfit,
        private readonly SumExpensesForDateRange $sumExpenses,
    ) {}

    public function handle(FinancialPeriod $period, string $distributableProfit): ProfitCalculation
    {
        $existing = ProfitCalculation::where('financial_period_id', $period->id)->first();

        if ($existing !== null && $existing->status === ProfitCalculationStatus::Finalized) {
            throw CannotRecalculateFinalizedProfitException::forPeriod($period->id);
        }

        $periodStart = $period->period_start->toDateString();
        $periodEnd = $period->period_end->toDateString();

        $sales = $this->grossProfit->forDateRange($periodStart, $periodEnd);

        $salaryExpense = bcadd('0', (string) EmployeeLedgerEntry::where('financial_period_id', $period->id)
            ->where('entry_type', EmployeeLedgerEntryType::SalaryAccrual)
            ->sum('amount'), 2);

        $commissionExpense = bcadd('0', (string) EmployeeLedgerEntry::where('financial_period_id', $period->id)
            ->where('entry_type', EmployeeLedgerEntryType::CommissionAccrual)
            ->sum('amount'), 2);

        $otherOperatingExpenses = $this->sumExpenses->handle($periodStart, $periodEnd);

        $netProfit = bcsub(
            $sales['total_gross_profit'],
            bcadd(bcadd($salaryExpense, $commissionExpense, 2), $otherOperatingExpenses, 2),
            2,
        );

        $this->assertDistributableInRange($distributableProfit, $netProfit);

        $retainedProfit = bcsub($netProfit, $distributableProfit, 2);

        return DB::transaction(function () use ($period, $sales, $salaryExpense, $commissionExpense, $otherOperatingExpenses, $netProfit, $distributableProfit, $retainedProfit, $existing) {
            $attributes = [
                'financial_period_id' => $period->id,
                'revenue' => $sales['total_revenue'],
                'cogs' => $sales['total_cogs'],
                'gross_profit' => $sales['total_gross_profit'],
                'salary_expense' => $salaryExpense,
                'commission_expense' => $commissionExpense,
                'other_operating_expenses' => $otherOperatingExpenses,
                'net_profit' => $netProfit,
                'distributable_profit' => $distributableProfit,
                'retained_profit' => $retainedProfit,
                'status' => ProfitCalculationStatus::Draft,
            ];

            if ($existing !== null) {
                $existing->update($attributes);

                return $existing->fresh();
            }

            return ProfitCalculation::create($attributes);
        });
    }

    private function assertDistributableInRange(string $distributable, string $netProfit): void
    {
        $ceiling = bccomp($netProfit, '0.00', 2) === 1 ? $netProfit : '0.00';

        if (bccomp($distributable, '0.00', 2) === -1 || bccomp($distributable, $ceiling, 2) === 1) {
            throw InvalidDistributableProfitException::outOfRange($distributable, $netProfit);
        }
    }
}
