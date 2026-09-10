<?php

namespace App\Modules\Partners\Exceptions;

use RuntimeException;

class PartnerHasOutstandingLoanException extends RuntimeException
{
    public static function forPartner(int $partnerId, string $outstandingTotal): self
    {
        return new self(
            "Cannot exit partner #{$partnerId}: they still owe [{$outstandingTotal}] on an outstanding loan. ".
            'Record the repayment (or write it off) before exiting them.'
        );
    }
}
