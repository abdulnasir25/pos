<?php

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Reports\Support\OutstandingBalancesReportBuilder;
use App\Modules\Reports\Support\SalesSummaryReportBuilder;
use App\Modules\Reports\Support\StockLevelReportBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends \App\Http\Controllers\Controller
{
    public function show(Request $request): Response
    {
        // Same data the Reports screen shows, but only for a user who
        // could already see it there — the dashboard is reachable by
        // any authenticated user, and revenue/profit isn't something
        // every role should see just by logging in.
        if (! $request->user()->hasPermission('reports.view')) {
            return Inertia::render('Dashboard');
        }

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        return Inertia::render('Dashboard', [
            'salesSummary' => app(SalesSummaryReportBuilder::class)->build($from, $to),
            'stockLevel' => app(StockLevelReportBuilder::class)->build(),
            'outstandingBalances' => app(OutstandingBalancesReportBuilder::class)->build(),
        ]);
    }
}
