<?php

namespace App\Modules\Sales\DTOs;

use App\Modules\Products\Models\Product;

/**
 * One line in the cart, as the caller (future POS UI, or a test) builds
 * it — quantities/prices in whatever unit the customer is buying in
 * (Suit, Meter, Roll). Conversion to the product's base unit happens
 * inside Inventory's own actions, not here — this DTO carries the
 * request, it doesn't do unit math.
 */
final readonly class CartLine
{
    public function __construct(
        public Product $product,
        public int $unitId,
        public string $quantity,
        public string $unitPrice,
        public string $discount = '0.00',
    ) {}

    public function lineTotal(): string
    {
        return bcsub(bcmul($this->quantity, $this->unitPrice, 4), $this->discount, 2);
    }
}
