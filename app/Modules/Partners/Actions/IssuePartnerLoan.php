<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Partners\Enums\LoanStatus;
use App\Modules\Partners\Enums\PartnerLedgerEntryType;
use App\Modules\Partners\Models\Partner;
use App\Modules\Partners\Models\PartnerLedgerEntry;
use App\Modules\Partners\Models\PartnerLoan;
use Illuminate\Support\Facades\DB;

/**
 * Records a partner lending money TO the business — a liability the
 * business owes back, never merged with capital (equity, no repayment
 * obligation).
 */
class IssuePartnerLoan
{
    public function handle(Partner $partner, string $principalAmount, string $issuedAt, int $createdBy, ?string $interestRate = null): PartnerLoan
    {
        return DB::transaction(function () use ($partner, $principalAmount, $issuedAt, $createdBy, $interestRate) {
            $loan = PartnerLoan::create([
                'partner_id' => $partner->id,
                'principal_amount' => $principalAmount,
                'interest_rate' => $interestRate,
                'status' => LoanStatus::Outstanding,
                'issued_at' => $issuedAt,
                'created_by' => $createdBy,
            ]);

            PartnerLedgerEntry::create([
                'partner_id' => $partner->id,
                'entry_type' => PartnerLedgerEntryType::LoanIssued,
                'amount' => $principalAmount,
                'reference_type' => PartnerLoan::class,
                'reference_id' => $loan->id,
            ]);

            return $loan;
        });
    }
}
