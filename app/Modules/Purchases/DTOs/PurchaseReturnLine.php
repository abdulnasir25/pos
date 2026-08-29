<?php

namespace App\Modules\Purchases\DTOs;

final readonly class PurchaseReturnLine
{
    public function __construct(
        public int $purchaseItemId,
        public string $quantity,
    ) {}
}
