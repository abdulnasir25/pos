<?php

namespace App\Modules\Purchases\DTOs;

final readonly class PurchasePaymentAllocation
{
    public function __construct(
        public int $paymentMethodId,
        public string $amount,
    ) {}
}
