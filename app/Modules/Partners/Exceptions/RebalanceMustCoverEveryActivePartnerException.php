<?php

namespace App\Modules\Partners\Exceptions;

use InvalidArgumentException;

class RebalanceMustCoverEveryActivePartnerException extends InvalidArgumentException
{
    public static function missing(array $partnerIds): self
    {
        $ids = implode(', ', $partnerIds);

        return new self(
            "Ownership rebalance must include every active partner. Missing partner id(s): [{$ids}]."
        );
    }
}
