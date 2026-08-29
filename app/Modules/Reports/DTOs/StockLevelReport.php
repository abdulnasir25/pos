<?php

namespace App\Modules\Reports\DTOs;

final readonly class StockLevelReport
{
    /**
     * @param  array<int, array{product: string, warehouse: string, quantity: string, average_cost: string, stock_value: string}>  $rows
     */
    public function __construct(
        public array $rows,
        public string $totalStockValue,
    ) {}
}
