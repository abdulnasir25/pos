<?php

namespace App\Modules\Expenses\Support;

use App\Modules\Expenses\Models\Expense;

/**
 * Feeds profit_calculations.other_operating_expenses (not implemented
 * yet — this is the piece that number will come from). whereDate(),
 * not where(): see CreateFinancialPeriod / AllocateProfitToPartners for
 * the boundary bug this avoids.
 */
class SumExpensesForDateRange
{
    public function handle(string $periodStart, string $periodEnd): string
    {
        $total = Expense::whereDate('expense_date', '>=', $periodStart)
            ->whereDate('expense_date', '<=', $periodEnd)
            ->sum('amount');

        return bcadd('0', (string) $total, 2);
    }
}
