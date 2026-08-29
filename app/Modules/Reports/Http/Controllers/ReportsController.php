<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use App\Modules\Reports\Exceptions\ProfitCalculationNotFoundException;
use App\Modules\Reports\Support\OutstandingBalancesReportBuilder;
use App\Modules\Reports\Support\ProfitAndLossReportBuilder;
use App\Modules\Reports\Support\SalesSummaryReportBuilder;
use App\Modules\Reports\Support\StockLevelReportBuilder;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every report here is read-only, computed fresh on each request from
 * the same Support classes the backend tests exercise — this
 * controller adds no business logic of its own, only query-param
 * plumbing and presentation.
 */
class ReportsController extends \App\Http\Controllers\Controller
{
    public function show(Request $request): Response
    {
        $from = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: now()->toDateString();
        $warehouseId = $request->query('warehouse_id');
        $financialPeriodId = $request->query('financial_period_id');

        $salesSummary = app(SalesSummaryReportBuilder::class)->build($from, $to);
        $stockLevel = app(StockLevelReportBuilder::class)->build($warehouseId ? (int) $warehouseId : null);
        $outstandingBalances = app(OutstandingBalancesReportBuilder::class)->build();

        $profitAndLoss = null;
        $profitAndLossError = null;

        if ($financialPeriodId) {
            $period = FinancialPeriod::find($financialPeriodId);

            if ($period !== null) {
                try {
                    $profitAndLoss = app(ProfitAndLossReportBuilder::class)->build($period);
                } catch (ProfitCalculationNotFoundException $e) {
                    $profitAndLossError = $e->getMessage();
                }
            }
        }

        return Inertia::render('Reports/Index', [
            'filters' => ['from' => $from, 'to' => $to, 'warehouse_id' => $warehouseId, 'financial_period_id' => $financialPeriodId],
            'warehouses' => Warehouse::where('status', 'active')->get(['id', 'name']),
            'financialPeriods' => FinancialPeriod::orderByDesc('period_start')->get(['id', 'period_start', 'period_end'])
                ->map(fn ($p) => ['id' => $p->id, 'label' => "{$p->period_start->toDateString()} – {$p->period_end->toDateString()}"]),
            'salesSummary' => $salesSummary,
            'stockLevel' => $stockLevel,
            'outstandingBalances' => $outstandingBalances,
            'profitAndLoss' => $profitAndLoss,
            'profitAndLossError' => $profitAndLossError,
        ]);
    }
}
