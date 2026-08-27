<?php

namespace App\Modules\Sales\Exceptions;

use RuntimeException;

class ReturnQuantityExceedsAvailableException extends RuntimeException
{
    public static function forSaleItem(int $saleItemId, string $requested, string $available): self
    {
        return new self(
            "Cannot return {$requested} of sale item [{$saleItemId}] — only {$available} remains eligible for return."
        );
    }
}
