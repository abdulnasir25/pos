<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Partners\Enums\LoanStatus;
use App\Modules\Partners\Enums\PartnerStatus;
use App\Modules\Partners\Exceptions\PartnerHasOutstandingLoanException;
use App\Modules\Partners\Models\Partner;

/**
 * Status change only, never a delete — matches the Employee termination
 * pattern. Does not touch ownership periods; closing out the exited
 * partner's ownership and rebalancing the remainder is a deliberate
 * separate step via RecordOwnershipRebalance, so the two concerns
 * (leaving the partnership vs. what happens to their %) stay
 * independently controlled.
 *
 * Refuses to exit a partner who still owes money on a loan — otherwise
 * they leave, the debt has no one actively responsible for chasing it
 * down, and it's easy to simply forget it's still owed.
 */
class ExitPartner
{
    public function handle(Partner $partner, string $exitedAt): Partner
    {
        $outstandingLoans = $partner->loans()->where('status', LoanStatus::Outstanding)->get();

        if ($outstandingLoans->isNotEmpty()) {
            $outstandingTotal = $outstandingLoans->reduce(
                fn (string $total, $loan) => bcadd($total, bcsub((string) $loan->principal_amount, (string) $loan->repayments()->sum('amount'), 2), 2),
                '0.00',
            );

            throw PartnerHasOutstandingLoanException::forPartner($partner->id, $outstandingTotal);
        }

        $partner->update([
            'status' => PartnerStatus::Exited,
            'exited_at' => $exitedAt,
        ]);

        return $partner;
    }
}
