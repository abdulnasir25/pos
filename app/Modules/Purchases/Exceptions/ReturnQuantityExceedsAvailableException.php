<?php

namespace App\Modules\Purchases\Exceptions;

use RuntimeException;

class ReturnQuantityExceedsAvailableException extends RuntimeException
{
    public static function forPurchaseItem(int $purchaseItemId, string $requested, string $available): self
    {
        return new self(
            "Cannot return {$requested} of purchase item [{$purchaseItemId}] — only {$available} remains eligible for return."
        );
    }
}
