<?php

namespace App\Modules\Sales\Exceptions;

use RuntimeException;

/**
 * Enforces the safer interim answer to a gap the Phase 1 requirements
 * review flagged and left explicitly open: a sale with no customer
 * attached cannot carry an outstanding balance, because a receivable
 * needs an owner. This is one line, named and documented, not a silent
 * assumption — replace it the moment that decision is actually made.
 */
class WalkInCreditNotAllowedException extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'A walk-in sale (no customer attached) must be paid in full. '
            .'Attach a customer to record a partial payment or outstanding balance.'
        );
    }
}
