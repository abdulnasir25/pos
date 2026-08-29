<?php

namespace App\Modules\Commission\Actions;

use App\Modules\Commission\Enums\CommissionEntryStatus;
use App\Modules\Commission\Exceptions\InvalidCommissionEntryTransitionException;
use App\Modules\Commission\Models\CommissionEntry;

class ApproveCommissionEntry
{
    public function handle(CommissionEntry $entry, int $approvedByUserId): CommissionEntry
    {
        if ($entry->status !== CommissionEntryStatus::Calculated) {
            throw InvalidCommissionEntryTransitionException::forTransition($entry->id, $entry->status->value, CommissionEntryStatus::Approved->value);
        }

        $entry->update([
            'status' => CommissionEntryStatus::Approved,
            'approved_by' => $approvedByUserId,
        ]);

        return $entry;
    }
}
