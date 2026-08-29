<?php

namespace App\Modules\Partners\Exceptions;

use InvalidArgumentException;

class RepaymentExceedsOutstandingBalanceException extends InvalidArgumentException
{
    public static function forLoan(int $loanId, string $attempted, string $outstanding): self
    {
        return new self(
            "Repayment of [{$attempted}] on loan #{$loanId} exceeds its outstanding balance of [{$outstanding}]."
        );
    }
}
