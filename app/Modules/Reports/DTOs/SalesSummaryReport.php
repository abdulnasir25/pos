<?php

namespace App\Modules\Reports\DTOs;

/**
 * Deliberately just data — no rendering, matching the Receipt DTO
 * pattern from Sales. A future UI/export turns this into a page or a
 * CSV; this module's job ends at assembling correct, complete data.
 */
final readonly class SalesSummaryReport
{
    /**
     * @param  array<int, array{method: string, amount: string}>  $byPaymentMethod
     */
    public function __construct(
        public string $periodStart,
        public string $periodEnd,
        public string $revenue,
        public string $cogs,
        public string $grossProfit,
        public int $saleCount,
        public array $byPaymentMethod,
    ) {}
}
