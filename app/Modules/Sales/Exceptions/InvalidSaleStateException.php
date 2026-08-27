<?php

namespace App\Modules\Sales\Exceptions;

use RuntimeException;

class InvalidSaleStateException extends RuntimeException
{
    public static function cannotCancel(int $saleId, string $currentStatus): self
    {
        return new self("Sale [{$saleId}] cannot be cancelled from status [{$currentStatus}] — only a confirmed sale can be cancelled.");
    }

    public static function cannotReturn(int $saleId, string $currentStatus): self
    {
        return new self("Sale [{$saleId}] cannot accept a return from status [{$currentStatus}] — only a confirmed sale can be returned against.");
    }
}
