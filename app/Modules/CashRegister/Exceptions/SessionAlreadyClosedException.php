<?php

namespace App\Modules\CashRegister\Exceptions;

use RuntimeException;

class SessionAlreadyClosedException extends RuntimeException
{
    public static function forSession(int $sessionId): self
    {
        return new self("Cash register session #{$sessionId} is already closed.");
    }
}
