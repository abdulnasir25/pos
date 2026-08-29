<?php

namespace App\Modules\ProfitCalculation\Models;

use App\Modules\ProfitCalculation\Enums\ProfitCalculationStatus;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per Financial Period — the finalized snapshot every other
 * financial module's totals feed into. Deliberately reads only from
 * the shared employee_ledger_entries feed and other modules' own
 * summing Support classes (CalculatePeriodGrossProfit,
 * SumExpensesForDateRange) rather than reaching into their tables
 * directly, so this module stays decoupled from their internals.
 *
 * Mutable only while status is Draft — recalculating overwrites the
 * row. Immutable once Finalized; see Actions/FinalizeProfitCalculation.
 */
class ProfitCalculation extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'financial_period_id',
        'revenue',
        'cogs',
        'gross_profit',
        'salary_expense',
        'commission_expense',
        'other_operating_expenses',
        'net_profit',
        'distributable_profit',
        'retained_profit',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'cogs' => 'decimal:2',
            'gross_profit' => 'decimal:2',
            'salary_expense' => 'decimal:2',
            'commission_expense' => 'decimal:2',
            'other_operating_expenses' => 'decimal:2',
            'net_profit' => 'decimal:2',
            'distributable_profit' => 'decimal:2',
            'retained_profit' => 'decimal:2',
            'status' => ProfitCalculationStatus::class,
        ];
    }
}
