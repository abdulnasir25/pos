<?php

namespace App\Modules\Reports\Support;

use App\Modules\Commission\Support\CalculatePeriodGrossProfit;
use App\Modules\Reports\DTOs\SalesSummaryReport;
use Illuminate\Support\Facades\DB;

/**
 * Reuses Commission's own revenue/cogs/gross_profit calculation for
 * this date range, rather than re-deriving it — the two numbers must
 * never be able to drift apart.
 */
class SalesSummaryReportBuilder
{
    public function __construct(private readonly CalculatePeriodGrossProfit $grossProfit) {}

    public function build(string $periodStart, string $periodEnd): SalesSummaryReport
    {
        $result = $this->grossProfit->forDateRange($periodStart, $periodEnd);

        $connection = DB::connection(config('tenancy.tenant_connection', 'tenant'));

        $saleCount = $connection->table('sales')
            ->whereIn('status', ['confirmed', 'refunded'])
            ->whereBetween('confirmed_at', ["{$periodStart} 00:00:00", "{$periodEnd} 23:59:59"])
            ->count();

        $byPaymentMethod = $connection->table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->whereIn('sales.status', ['confirmed', 'refunded'])
            ->whereBetween('sales.confirmed_at', ["{$periodStart} 00:00:00", "{$periodEnd} 23:59:59"])
            ->selectRaw('payment_methods.name as method, SUM(sale_payments.amount) as amount')
            ->groupBy('payment_methods.name')
            ->get()
            ->map(fn ($row) => ['method' => $row->method, 'amount' => bcadd('0', (string) $row->amount, 2)])
            ->all();

        return new SalesSummaryReport(
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            revenue: $result['total_revenue'],
            cogs: $result['total_cogs'],
            grossProfit: $result['total_gross_profit'],
            saleCount: $saleCount,
            byPaymentMethod: $byPaymentMethod,
        );
    }
}
