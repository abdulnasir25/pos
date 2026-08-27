<?php

namespace App\Modules\Sales\Exceptions;

use RuntimeException;

class OverpaymentException extends RuntimeException
{
    public static function forSale(string $total, string $paid): self
    {
        return new self("Payments totalling {$paid} exceed the sale total of {$total}.");
    }
}
