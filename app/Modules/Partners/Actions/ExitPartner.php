<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Partners\Enums\PartnerStatus;
use App\Modules\Partners\Models\Partner;

/**
 * Status change only, never a delete — matches the Employee termination
 * pattern. Does not touch ownership periods; closing out the exited
 * partner's ownership and rebalancing the remainder is a deliberate
 * separate step via RecordOwnershipRebalance, so the two concerns
 * (leaving the partnership vs. what happens to their %) stay
 * independently controlled.
 */
class ExitPartner
{
    public function handle(Partner $partner, string $exitedAt): Partner
    {
        $partner->update([
            'status' => PartnerStatus::Exited,
            'exited_at' => $exitedAt,
        ]);

        return $partner;
    }
}
