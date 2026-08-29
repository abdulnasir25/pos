<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Partners\Enums\LoanStatus;
use App\Modules\Partners\Enums\PartnerLedgerEntryType;
use App\Modules\Partners\Exceptions\RepaymentExceedsOutstandingBalanceException;
use App\Modules\Partners\Models\PartnerLedgerEntry;
use App\Modules\Partners\Models\PartnerLoan;
use App\Modules\Partners\Models\PartnerLoanRepayment;
use Illuminate\Support\Facades\DB;

class RecordLoanRepayment
{
    public function handle(PartnerLoan $loan, string $amount, string $repaidAt, int $createdBy): PartnerLoanRepayment
    {
        return DB::transaction(function () use ($loan, $amount, $repaidAt, $createdBy) {
            $alreadyRepaid = $loan->repayments()->sum('amount');
            $outstanding = bcsub((string) $loan->principal_amount, (string) $alreadyRepaid, 2);

            if (bccomp($amount, $outstanding, 2) === 1) {
                throw RepaymentExceedsOutstandingBalanceException::forLoan($loan->id, $amount, $outstanding);
            }

            $repayment = PartnerLoanRepayment::create([
                'partner_loan_id' => $loan->id,
                'amount' => $amount,
                'repaid_at' => $repaidAt,
                'created_by' => $createdBy,
            ]);

            PartnerLedgerEntry::create([
                'partner_id' => $loan->partner_id,
                'entry_type' => PartnerLedgerEntryType::LoanRepayment,
                'amount' => bcmul($amount, '-1', 2),
                'reference_type' => PartnerLoanRepayment::class,
                'reference_id' => $repayment->id,
            ]);

            if (bccomp($amount, $outstanding, 2) === 0) {
                $loan->update(['status' => LoanStatus::Repaid]);
            }

            return $repayment;
        });
    }
}
