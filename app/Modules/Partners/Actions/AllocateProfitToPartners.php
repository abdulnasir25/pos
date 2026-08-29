<?php

namespace App\Modules\Partners\Actions;

use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use App\Modules\Partners\Exceptions\NoOwnershipDataForDateException;
use App\Modules\Partners\Models\PartnerOwnershipPeriod;
use App\Modules\Partners\Models\PartnerProfitAllocation;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Splits a period's distributable profit across partners by ownership
 * percentage, honoring any ownership change that happened mid-period —
 * each contiguous stretch of constant ownership becomes its own
 * sub-range, split proportionally by its share of the period's total
 * days. distributable_profit is an explicit input here rather than
 * read from profit_calculations, since that table/module doesn't exist
 * yet — this action is the "partner_id × percentage → amount" piece,
 * independent of how the number was arrived at.
 *
 * Rounding note: each sub-range's amount, and each partner's share of
 * it, is rounded to 2dp independently — the sum of all allocated_amount
 * rows may differ from distributable_profit by a cent or two on an
 * uneven split. No rounding-adjustment line is written; out of scope
 * until this is wired to a real profit-calculation module.
 */
class AllocateProfitToPartners
{
    /**
     * @return Collection<int, PartnerProfitAllocation>
     */
    public function handle(FinancialPeriod $period, string $distributableProfit): Collection
    {
        return DB::transaction(function () use ($period, $distributableProfit) {
            $subRanges = $this->buildSubRanges($period);

            $totalDays = $period->period_start->diffInDays($period->period_end) + 1;

            $allocations = new Collection;

            foreach ($subRanges as $subRange) {
                $daysInSubRange = $subRange['start']->diffInDays($subRange['end']) + 1;
                $amountForSubRange = bcmul(
                    $distributableProfit,
                    bcdiv((string) $daysInSubRange, (string) $totalDays, 10),
                    2,
                );

                foreach ($subRange['percentages'] as $partnerId => $percentage) {
                    $allocatedAmount = bcmul($amountForSubRange, bcdiv((string) $percentage, '100', 10), 2);

                    $allocations->push(PartnerProfitAllocation::create([
                        'financial_period_id' => $period->id,
                        'partner_id' => $partnerId,
                        'sub_range_start' => $subRange['start']->toDateString(),
                        'sub_range_end' => $subRange['end']->toDateString(),
                        'applied_percentage' => $percentage,
                        'allocated_amount' => $allocatedAmount,
                    ]));
                }
            }

            return $allocations;
        });
    }

    /**
     * @return list<array{start: \Carbon\Carbon, end: \Carbon\Carbon, percentages: array<int, string>}>
     */
    private function buildSubRanges(FinancialPeriod $period): array
    {
        $ownershipPeriods = PartnerOwnershipPeriod::where('effective_from', '<=', $period->period_end)
            ->where(function ($query) use ($period) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $period->period_start);
            })
            ->get();

        $subRanges = [];
        $currentStart = null;
        $currentKey = null;
        $currentPercentages = [];

        foreach (CarbonPeriod::create($period->period_start, $period->period_end) as $day) {
            $percentagesToday = [];

            foreach ($ownershipPeriods as $ownershipPeriod) {
                if ($ownershipPeriod->effective_from->lte($day)
                    && ($ownershipPeriod->effective_to === null || $ownershipPeriod->effective_to->gte($day))) {
                    $percentagesToday[$ownershipPeriod->partner_id] = (string) $ownershipPeriod->percentage;
                }
            }

            if ($percentagesToday === []) {
                throw NoOwnershipDataForDateException::forDate($day->toDateString());
            }

            ksort($percentagesToday);
            $key = json_encode($percentagesToday);

            if ($key !== $currentKey) {
                if ($currentStart !== null) {
                    $subRanges[] = [
                        'start' => $currentStart,
                        'end' => $day->clone()->subDay(),
                        'percentages' => $currentPercentages,
                    ];
                }

                $currentStart = $day->clone();
                $currentKey = $key;
                $currentPercentages = $percentagesToday;
            }
        }

        if ($currentStart !== null) {
            $subRanges[] = [
                'start' => $currentStart,
                'end' => $period->period_end->clone(),
                'percentages' => $currentPercentages,
            ];
        }

        return $subRanges;
    }
}
