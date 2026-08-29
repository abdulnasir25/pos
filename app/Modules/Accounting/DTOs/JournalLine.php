<?php

namespace App\Modules\Accounting\DTOs;

/**
 * One side of one account touched by a journal entry — exactly one of
 * debit/credit must be non-zero, never both.
 */
final readonly class JournalLine
{
    public function __construct(
        public int $accountId,
        public string $debit = '0.00',
        public string $credit = '0.00',
    ) {}
}
