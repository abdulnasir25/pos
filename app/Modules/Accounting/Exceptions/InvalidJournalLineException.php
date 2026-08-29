<?php

namespace App\Modules\Accounting\Exceptions;

use InvalidArgumentException;

class InvalidJournalLineException extends InvalidArgumentException
{
    public static function mustBeDebitOrCredit(int $lineIndex): self
    {
        return new self(
            "Journal line #{$lineIndex} must have either a debit or a credit amount, never both, and never neither."
        );
    }
}
