<?php

namespace App\Modules\FinancialPeriods\Exceptions;

use RuntimeException;

class OverlappingPeriodException extends RuntimeException
{
    public static function forRange(string $start, string $end): self
    {
        return new self(
            "A financial period already exists overlapping [{$start} – {$end}]. "
            .'Financial periods must not share any date with an existing period.'
        );
    }
}
