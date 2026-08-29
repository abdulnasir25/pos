<?php

namespace App\Modules\Commission\Actions;

use App\Modules\Commission\Enums\CommissionEntryStatus;
use App\Modules\Commission\Exceptions\InvalidCommissionEntryTransitionException;
use App\Modules\Commission\Models\CommissionEntry;
use App\Modules\Employees\Enums\EmployeeLedgerEntryType;
use App\Modules\Employees\Models\EmployeeLedgerEntry;
use Illuminate\Support\Facades\DB;

class RecordCommissionPayment
{
    public function handle(CommissionEntry $entry): CommissionEntry
    {
        if ($entry->status !== CommissionEntryStatus::Finalized) {
            throw InvalidCommissionEntryTransitionException::forTransition($entry->id, $entry->status->value, CommissionEntryStatus::Paid->value);
        }

        return DB::transaction(function () use ($entry) {
            EmployeeLedgerEntry::create([
                'employee_id' => $entry->employee_id,
                'entry_type' => EmployeeLedgerEntryType::CommissionPayment,
                'amount' => bcmul((string) $entry->commission_amount, '-1', 2),
                'financial_period_id' => $entry->financial_period_id,
                'reference_type' => CommissionEntry::class,
                'reference_id' => $entry->id,
            ]);

            $entry->update(['status' => CommissionEntryStatus::Paid]);

            return $entry;
        });
    }
}
