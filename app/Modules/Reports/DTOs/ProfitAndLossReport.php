<?php

namespace App\Modules\Reports\DTOs;

final readonly class ProfitAndLossReport
{
    public function __construct(
        public string $periodStart,
        public string $periodEnd,
        public string $revenue,
        public string $cogs,
        public string $grossProfit,
        public string $salaryExpense,
        public string $commissionExpense,
        public string $otherOperatingExpenses,
        public string $netProfit,
        public string $distributableProfit,
        public string $retainedProfit,
        public string $status,
    ) {}
}
