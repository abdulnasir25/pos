<?php

namespace App\Modules\CashRegister\Exceptions;

use RuntimeException;

class SessionAlreadyOpenException extends RuntimeException
{
    public static function forAccount(int $financialAccountId): self
    {
        return new self("Financial account #{$financialAccountId} already has an open cash register session.");
    }
}
