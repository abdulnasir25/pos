<?php

namespace App\Modules\Purchases\Exceptions;

use RuntimeException;

class OverpaymentException extends RuntimeException
{
    public static function forPurchase(string $total, string $paid): self
    {
        return new self("Payments totalling {$paid} exceed the purchase total of {$total}.");
    }
}
