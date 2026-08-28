<?php

namespace App\Modules\Employees\Exceptions;

use InvalidArgumentException;

class InvalidCompensationRangeException extends InvalidArgumentException
{
    public static function endBeforeStart(string $effectiveFrom, string $effectiveTo): self
    {
        return new self("effective_to [{$effectiveTo}] cannot be before effective_from [{$effectiveFrom}].");
    }
}
