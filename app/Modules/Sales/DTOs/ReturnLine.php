<?php

namespace App\Modules\Sales\DTOs;

final readonly class ReturnLine
{
    public function __construct(
        public int $saleItemId,
        public string $quantity,
    ) {}
}
