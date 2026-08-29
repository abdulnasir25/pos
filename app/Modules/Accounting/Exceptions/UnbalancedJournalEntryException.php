<?php

namespace App\Modules\Accounting\Exceptions;

use InvalidArgumentException;

class UnbalancedJournalEntryException extends InvalidArgumentException
{
    public static function forTotals(string $totalDebits, string $totalCredits): self
    {
        return new self(
            "A journal entry must balance — total debits [{$totalDebits}] do not equal total credits [{$totalCredits}]."
        );
    }
}
