<?php

namespace App\Modules\Purchases\DTOs;

use App\Modules\Products\Models\Product;

final readonly class PurchaseLine
{
    public function __construct(
        public Product $product,
        public int $unitId,
        public string $quantity,
        public string $unitCost,
        public string $discount = '0.00',
    ) {}

    public function lineTotal(): string
    {
        return bcsub(bcmul($this->quantity, $this->unitCost, 4), $this->discount, 2);
    }
}
