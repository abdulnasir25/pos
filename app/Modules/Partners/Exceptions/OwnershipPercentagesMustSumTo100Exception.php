<?php

namespace App\Modules\Partners\Exceptions;

use InvalidArgumentException;

class OwnershipPercentagesMustSumTo100Exception extends InvalidArgumentException
{
    public static function forSum(string $sum, string $effectiveFrom): self
    {
        return new self(
            "Ownership percentages for all active partners must sum to 100 as of [{$effectiveFrom}], ".
            "got [{$sum}]."
        );
    }
}
