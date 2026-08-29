<?php

namespace App\Modules\Commission\Actions;

use App\Modules\Commission\Enums\CommissionEntryStatus;
use App\Modules\Commission\Exceptions\InvalidCommissionEntryTransitionException;
use App\Modules\Commission\Models\CommissionEntry;

/**
 * The last transition before this entry becomes truly immutable — any
 * correction needed after this point is a CommissionCorrection landing
 * in the currently open period, never an edit here.
 */
class FinalizeCommissionEntry
{
    public function handle(CommissionEntry $entry): CommissionEntry
    {
        if ($entry->status !== CommissionEntryStatus::Approved) {
            throw InvalidCommissionEntryTransitionException::forTransition($entry->id, $entry->status->value, CommissionEntryStatus::Finalized->value);
        }

        $entry->update(['status' => CommissionEntryStatus::Finalized]);

        return $entry;
    }
}
