<?php

namespace App\Modules\Reports\DTOs;

final readonly class OutstandingBalancesReport
{
    /**
     * @param  array<int, array{name: string, balance: string}>  $customers
     * @param  array<int, array{name: string, balance: string}>  $suppliers
     */
    public function __construct(
        public array $customers,
        public string $totalReceivable,
        public array $suppliers,
        public string $totalPayable,
    ) {}
}
