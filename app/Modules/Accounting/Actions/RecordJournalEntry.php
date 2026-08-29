<?php

namespace App\Modules\Accounting\Actions;

use App\Modules\Accounting\Exceptions\InvalidJournalLineException;
use App\Modules\Accounting\Exceptions\UnbalancedJournalEntryException;
use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

/**
 * The only supported way to post a journal entry. Enforces the two
 * double-entry invariants at the application layer (no DB constraint
 * can express either): every line is a debit XOR a credit, never both
 * or neither, and the whole entry's debits must equal its credits.
 * Immutable once created — a correction is a new, reversing entry.
 *
 * @param  \App\Modules\Accounting\DTOs\JournalLine[]  $lines
 */
class RecordJournalEntry
{
    public function handle(
        string $entryDate,
        array $lines,
        int $createdBy,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): JournalEntry {
        $totalDebits = '0.00';
        $totalCredits = '0.00';

        foreach ($lines as $index => $line) {
            $hasDebit = bccomp($line->debit, '0.00', 2) === 1;
            $hasCredit = bccomp($line->credit, '0.00', 2) === 1;

            if ($hasDebit === $hasCredit) {
                throw InvalidJournalLineException::mustBeDebitOrCredit($index);
            }

            $totalDebits = bcadd($totalDebits, $line->debit, 2);
            $totalCredits = bcadd($totalCredits, $line->credit, 2);
        }

        if (bccomp($totalDebits, $totalCredits, 2) !== 0) {
            throw UnbalancedJournalEntryException::forTotals($totalDebits, $totalCredits);
        }

        return DB::transaction(function () use ($entryDate, $lines, $createdBy, $description, $referenceType, $referenceId) {
            $entry = JournalEntry::create([
                'entry_date' => $entryDate,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $createdBy,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'account_id' => $line->accountId,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                ]);
            }

            return $entry->fresh('lines');
        });
    }
}
