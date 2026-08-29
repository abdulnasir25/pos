<?php

namespace App\Modules\Partners\Exceptions;

use InvalidArgumentException;

class InvalidOwnershipDateRangeException extends InvalidArgumentException
{
    public static function newEffectiveDateBeforeExisting(int $partnerId, string $effectiveFrom, string $existingEffectiveFrom): self
    {
        return new self(
            "New effective_from [{$effectiveFrom}] for partner #{$partnerId} is not after their existing ".
            "open ownership period, which started [{$existingEffectiveFrom}]."
        );
    }
}
