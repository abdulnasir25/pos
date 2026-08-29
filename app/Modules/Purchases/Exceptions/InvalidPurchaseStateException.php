<?php

namespace App\Modules\Purchases\Exceptions;

use RuntimeException;

class InvalidPurchaseStateException extends RuntimeException
{
    public static function cannotCancel(int $purchaseId, string $currentStatus): self
    {
        return new self("Purchase [{$purchaseId}] cannot be cancelled from status [{$currentStatus}] — only a confirmed purchase can be cancelled.");
    }

    public static function cannotReturn(int $purchaseId, string $currentStatus): self
    {
        return new self("Purchase [{$purchaseId}] cannot accept a return from status [{$currentStatus}] — only a confirmed purchase can be returned against.");
    }
}
