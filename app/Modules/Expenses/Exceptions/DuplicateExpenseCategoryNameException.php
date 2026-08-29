<?php

namespace App\Modules\Expenses\Exceptions;

use InvalidArgumentException;

class DuplicateExpenseCategoryNameException extends InvalidArgumentException
{
    public static function forName(string $name): self
    {
        return new self("An expense category named [{$name}] already exists.");
    }
}
