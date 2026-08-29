<?php

namespace App\Modules\Commission\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tenant-wide gross profit for a date range: every confirmed (or later
 * refunded) sale's line revenue minus cost, net of whatever has been
 * returned against it so far — regardless of when the return itself
 * was recorded, matching the confirmed rule that a same-period return
 * simply isn't included once it's netted out before calculation.
 *
 * Revenue is netted the same way ReturnSaleItems computes a refund:
 * proportionally by (returned_qty / original_qty) against the line's
 * already-discounted line_total. Cost is netted directly, since
 * unit_cost_snapshot is already a per-unit figure with no discount
 * involved.
 */
class CalculatePeriodGrossProfit
{
    /**
     * @return array{total_revenue: string, total_cogs: string, total_gross_profit: string, per_sale: Collection<int, array{revenue: string, cogs: string, gross_profit: string}>}
     */
    public function forDateRange(string $periodStart, string $periodEnd): array
    {
        $connection = DB::connection(config('tenancy.tenant_connection', 'tenant'));

        $saleItems = $connection->table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sales.status', ['confirmed', 'refunded'])
            ->whereBetween('sales.confirmed_at', ["{$periodStart} 00:00:00", "{$periodEnd} 23:59:59"])
            ->select('sale_items.id', 'sale_items.sale_id', 'sale_items.quantity', 'sale_items.unit_cost_snapshot', 'sale_items.line_total')
            ->get();

        if ($saleItems->isEmpty()) {
            return [
                'total_revenue' => '0.00',
                'total_cogs' => '0.00',
                'total_gross_profit' => '0.00',
                'per_sale' => new Collection,
            ];
        }

        $returnedQuantities = $connection->table('sale_return_items')
            ->whereIn('sale_item_id', $saleItems->pluck('id'))
            ->selectRaw('sale_item_id, SUM(quantity) as returned_quantity')
            ->groupBy('sale_item_id')
            ->get()
            ->keyBy('sale_item_id');

        $perSale = new Collection;

        foreach ($saleItems as $item) {
            $returnedQty = (string) ($returnedQuantities->get($item->id)?->returned_quantity ?? '0');

            $returnedRevenue = bccomp($returnedQty, '0', 4) === 1
                ? bcmul((string) $item->line_total, bcdiv($returnedQty, (string) $item->quantity, 6), 2)
                : '0.00';

            $returnedCogs = bcmul($returnedQty, (string) $item->unit_cost_snapshot, 2);

            $revenueNet = bcsub((string) $item->line_total, $returnedRevenue, 2);
            $cogsNet = bcsub(bcmul((string) $item->quantity, (string) $item->unit_cost_snapshot, 2), $returnedCogs, 2);

            $existing = $perSale->get($item->sale_id, ['revenue' => '0.00', 'cogs' => '0.00']);

            $perSale->put($item->sale_id, [
                'revenue' => bcadd($existing['revenue'], $revenueNet, 2),
                'cogs' => bcadd($existing['cogs'], $cogsNet, 2),
            ]);
        }

        $perSale = $perSale->map(fn ($sale) => [
            'revenue' => $sale['revenue'],
            'cogs' => $sale['cogs'],
            'gross_profit' => bcsub($sale['revenue'], $sale['cogs'], 2),
        ]);

        $totalRevenue = $perSale->reduce(fn ($carry, $sale) => bcadd($carry, $sale['revenue'], 2), '0.00');
        $totalCogs = $perSale->reduce(fn ($carry, $sale) => bcadd($carry, $sale['cogs'], 2), '0.00');

        return [
            'total_revenue' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_gross_profit' => bcsub($totalRevenue, $totalCogs, 2),
            'per_sale' => $perSale,
        ];
    }
}
