<?php

namespace App\Modules\Sales\DTOs;

final readonly class PaymentAllocation
{
    public function __construct(
        public int $paymentMethodId,
        public string $amount,
    ) {}
}
