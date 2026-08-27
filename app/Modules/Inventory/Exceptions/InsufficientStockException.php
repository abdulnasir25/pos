<?php

namespace App\Modules\Inventory\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public static function forProduct(int $productId, int $warehouseId, string $requested): self
    {
        return new self(
            "Insufficient stock for product [{$productId}] in warehouse [{$warehouseId}]: "
            ."requested {$requested} base units, which is more than is currently on hand."
        );
    }
}
