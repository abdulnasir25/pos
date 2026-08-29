<?php

namespace App\Modules\Commission\Exceptions;

use RuntimeException;

class InvalidCommissionEntryTransitionException extends RuntimeException
{
    public static function forTransition(int $entryId, string $from, string $to): self
    {
        return new self("Commission entry #{$entryId} cannot move from [{$from}] to [{$to}].");
    }
}
