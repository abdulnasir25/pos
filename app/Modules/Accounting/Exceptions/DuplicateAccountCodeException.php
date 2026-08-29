<?php

namespace App\Modules\Accounting\Exceptions;

use InvalidArgumentException;

class DuplicateAccountCodeException extends InvalidArgumentException
{
    public static function forCode(string $code): self
    {
        return new self("An account with code [{$code}] already exists.");
    }
}
