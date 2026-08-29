<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Partners\Enums\CapitalEntryType;
use App\Modules\Partners\Enums\PartnerLedgerEntryType;
use App\Modules\Partners\Models\Partner;
use App\Modules\Partners\Models\PartnerCapitalEntry;
use App\Modules\Partners\Models\PartnerLedgerEntry;
use Illuminate\Support\Facades\DB;

class RecordCapitalWithdrawal
{
    public function handle(Partner $partner, string $amount, string $entryDate, int $createdBy): PartnerCapitalEntry
    {
        return DB::transaction(function () use ($partner, $amount, $entryDate, $createdBy) {
            $entry = PartnerCapitalEntry::create([
                'partner_id' => $partner->id,
                'entry_type' => CapitalEntryType::Withdrawal,
                'amount' => $amount,
                'entry_date' => $entryDate,
                'created_by' => $createdBy,
            ]);

            PartnerLedgerEntry::create([
                'partner_id' => $partner->id,
                'entry_type' => PartnerLedgerEntryType::CapitalWithdrawal,
                'amount' => bcmul($amount, '-1', 2),
                'reference_type' => PartnerCapitalEntry::class,
                'reference_id' => $entry->id,
            ]);

            return $entry;
        });
    }
}
