<?php

namespace App\Modules\Expenses\Exceptions;

use InvalidArgumentException;

class InvalidExpenseAmountException extends InvalidArgumentException
{
    public static function mustBePositive(string $amount): self
    {
        return new self("Expense amount must be positive, got [{$amount}]. Use RecordExpenseCorrection for a reduction.");
    }
}
