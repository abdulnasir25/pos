<?php

namespace App\Modules\Sales\DTOs;

/**
 * The printable representation of a confirmed sale. Deliberately just
 * data — no rendering, no PDF, no template. A future POS UI or a print
 * service turns this into an actual receipt; this module's job ends at
 * assembling correct, complete data for one.
 */
final readonly class Receipt
{
    public function __construct(
        public string $referenceNo,
        public string $issuedAt,
        public string $customerName,
        public array $lines,
        public string $subtotal,
        public string $discountTotal,
        public string $total,
        public array $payments,
        public string $paidTotal,
        public string $balanceDue,
    ) {}
}
