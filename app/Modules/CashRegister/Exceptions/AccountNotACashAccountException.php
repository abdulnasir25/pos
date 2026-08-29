<?php

namespace App\Modules\CashRegister\Exceptions;

use InvalidArgumentException;

class AccountNotACashAccountException extends InvalidArgumentException
{
    public static function forAccount(int $financialAccountId, string $actualType): self
    {
        return new self(
            "Financial account #{$financialAccountId} is a [{$actualType}] account — a cash register ".
            'session can only wrap a cash account.'
        );
    }
}
