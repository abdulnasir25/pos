<?php

namespace App\Modules\Partners\Exceptions;

use RuntimeException;

class NoOwnershipDataForDateException extends RuntimeException
{
    public static function forDate(string $date): self
    {
        return new self("No partner ownership data covers [{$date}] — cannot allocate profit for this date.");
    }
}
