<?php

namespace App\Modules\Partners\Actions;

use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use App\Modules\Partners\Enums\PartnerLedgerEntryType;
use App\Modules\Partners\Models\Partner;
use App\Modules\Partners\Models\PartnerDistribution;
use App\Modules\Partners\Models\PartnerLedgerEntry;
use Illuminate\Support\Facades\DB;

/**
 * The actual payout — distinct from PartnerProfitAllocation (the
 * entitlement calculation). Can be recorded even after the allocating
 * period has closed, since paying out an already-allocated amount
 * doesn't change the closed period's figures.
 */
class RecordPartnerDistribution
{
    public function handle(Partner $partner, FinancialPeriod $period, string $amount, int $paymentMethodId, string $paidAt, int $createdBy): PartnerDistribution
    {
        return DB::transaction(function () use ($partner, $period, $amount, $paymentMethodId, $paidAt, $createdBy) {
            $distribution = PartnerDistribution::create([
                'partner_id' => $partner->id,
                'financial_period_id' => $period->id,
                'amount' => $amount,
                'payment_method_id' => $paymentMethodId,
                'paid_at' => $paidAt,
                'created_by' => $createdBy,
            ]);

            PartnerLedgerEntry::create([
                'partner_id' => $partner->id,
                'entry_type' => PartnerLedgerEntryType::ProfitDistribution,
                'amount' => bcmul($amount, '-1', 2),
                'reference_type' => PartnerDistribution::class,
                'reference_id' => $distribution->id,
            ]);

            return $distribution;
        });
    }
}
