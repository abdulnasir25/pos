<?php

namespace App\Modules\Commission\Actions;

use App\Modules\Commission\Enums\CommissionCorrectionReason;
use App\Modules\Commission\Exceptions\CorrectionMustLandInAnOpenPeriodException;
use App\Modules\Commission\Models\CommissionCorrection;
use App\Modules\Commission\Models\CommissionEntry;
use App\Modules\Employees\Enums\EmployeeLedgerEntryType;
use App\Modules\Employees\Models\EmployeeLedgerEntry;
use App\Modules\FinancialPeriods\Enums\FinancialPeriodStatus;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use Illuminate\Support\Facades\DB;

/**
 * The confirmed forward-correction mechanism: never edits the original
 * (already-finalized) commission_entries row — always lands in
 * whichever period is currently open, signed negative for a clawback.
 */
class RecordCommissionCorrection
{
    public function handle(
        CommissionEntry $originalEntry,
        FinancialPeriod $currentOpenPeriod,
        string $amount,
        CommissionCorrectionReason $reason,
        int $createdBy,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): CommissionCorrection {
        if ($currentOpenPeriod->status !== FinancialPeriodStatus::Open) {
            throw CorrectionMustLandInAnOpenPeriodException::forPeriod($currentOpenPeriod->id, $currentOpenPeriod->status->value);
        }

        return DB::transaction(function () use ($originalEntry, $currentOpenPeriod, $amount, $reason, $createdBy, $referenceType, $referenceId) {
            $correction = CommissionCorrection::create([
                'employee_id' => $originalEntry->employee_id,
                'original_commission_entry_id' => $originalEntry->id,
                'financial_period_id' => $currentOpenPeriod->id,
                'reason' => $reason,
                'amount' => $amount,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $createdBy,
            ]);

            EmployeeLedgerEntry::create([
                'employee_id' => $originalEntry->employee_id,
                'entry_type' => EmployeeLedgerEntryType::CommissionCorrection,
                'amount' => $amount,
                'financial_period_id' => $currentOpenPeriod->id,
                'reference_type' => CommissionCorrection::class,
                'reference_id' => $correction->id,
            ]);

            return $correction;
        });
    }
}
