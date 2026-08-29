<?php

namespace App\Modules\Commission\Actions;

use App\Modules\Commission\Enums\CommissionEntryStatus;
use App\Modules\Commission\Exceptions\CommissionAlreadyCalculatedException;
use App\Modules\Commission\Models\CommissionEntry;
use App\Modules\Commission\Models\CommissionRule;
use App\Modules\Commission\Support\CalculatePeriodGrossProfit;
use App\Modules\Employees\Enums\EmployeeLedgerEntryType;
use App\Modules\Employees\Models\EmployeeLedgerEntry;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes commission for every employee who has an active
 * commission_rules row covering this period, against the tenant's
 * WHOLE gross profit for the period (confirmed correction — see the
 * 0012 migration's docblock, not each employee's own sales). One
 * commission_entries row per employee-rule pair, immutable once
 * created here; a rate change or a return after this runs is a new
 * rule / a CommissionCorrection, never an edit to what this produces.
 */
class CalculateCommissionForPeriod
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly CalculatePeriodGrossProfit $grossProfit,
    ) {}

    /**
     * @return Collection<int, CommissionEntry>
     */
    public function handle(FinancialPeriod $period): Collection
    {
        $this->tenant->get();

        if (CommissionEntry::where('financial_period_id', $period->id)->exists()) {
            throw CommissionAlreadyCalculatedException::forPeriod($period->id);
        }

        $periodStart = $period->period_start->toDateString();
        $periodEnd = $period->period_end->toDateString();

        $rules = CommissionRule::whereNotNull('employee_id')
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $periodEnd)
            ->where(function ($query) use ($periodStart) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $periodStart);
            })
            ->get();

        if ($rules->isEmpty()) {
            return new Collection;
        }

        $result = $this->grossProfit->forDateRange($periodStart, $periodEnd);

        return DB::transaction(function () use ($rules, $result, $period) {
            $entries = new Collection;

            foreach ($rules as $rule) {
                $commissionAmount = bcmul($result['total_gross_profit'], bcdiv((string) $rule->rate, '100', 10), 2);

                $entry = CommissionEntry::create([
                    'employee_id' => $rule->employee_id,
                    'commission_rule_id' => $rule->id,
                    'financial_period_id' => $period->id,
                    'eligible_gross_profit' => $result['total_gross_profit'],
                    'rate_applied' => $rule->rate,
                    'commission_amount' => $commissionAmount,
                    'status' => CommissionEntryStatus::Calculated,
                ]);

                foreach ($result['per_sale'] as $saleId => $saleFigures) {
                    $entry->saleLines()->create([
                        'sale_id' => $saleId,
                        'eligible_gross_profit' => $saleFigures['gross_profit'],
                    ]);
                }

                EmployeeLedgerEntry::create([
                    'employee_id' => $rule->employee_id,
                    'entry_type' => EmployeeLedgerEntryType::CommissionAccrual,
                    'amount' => $commissionAmount,
                    'financial_period_id' => $period->id,
                    'reference_type' => CommissionEntry::class,
                    'reference_id' => $entry->id,
                ]);

                $entries->push($entry);
            }

            return $entries;
        });
    }
}
