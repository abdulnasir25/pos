<?php

namespace App\Modules\Employees\Exceptions;

use RuntimeException;

class OverlappingCompensationException extends RuntimeException
{
    public static function forEmployee(int $employeeId, string $effectiveFrom): self
    {
        return new self(
            "Employee [{$employeeId}] already has compensation covering [{$effectiveFrom}] "
            .'that is not simply the currently-open record being superseded.'
        );
    }
}
