<?php

namespace App\Modules\Commission\Exceptions;

use RuntimeException;

class CorrectionMustLandInAnOpenPeriodException extends RuntimeException
{
    public static function forPeriod(int $financialPeriodId, string $status): self
    {
        return new self(
            "Commission correction must land in an open financial period; period #{$financialPeriodId} is [{$status}]."
        );
    }
}
