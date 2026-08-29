<?php

namespace App\Modules\Reports\Support;

use App\Modules\Reports\DTOs\StockLevelReport;
use Illuminate\Support\Facades\DB;

class StockLevelReportBuilder
{
    public function build(?int $warehouseId = null): StockLevelReport
    {
        $connection = DB::connection(config('tenancy.tenant_connection', 'tenant'));

        $query = $connection->table('stock_levels')
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
            ->where('stock_levels.quantity_base_unit', '<>', 0);

        if ($warehouseId !== null) {
            $query->where('stock_levels.warehouse_id', $warehouseId);
        }

        $rows = $query
            ->selectRaw('products.name as product, warehouses.name as warehouse, stock_levels.quantity_base_unit as quantity, stock_levels.average_cost as average_cost')
            ->orderBy('products.name')
            ->get();

        $totalStockValue = '0.00';
        $formattedRows = [];

        foreach ($rows as $row) {
            $quantity = bcadd('0', (string) $row->quantity, 4);
            $averageCost = bcadd('0', (string) $row->average_cost, 4);
            $stockValue = bcmul($quantity, $averageCost, 2);

            $totalStockValue = bcadd($totalStockValue, $stockValue, 2);

            $formattedRows[] = [
                'product' => $row->product,
                'warehouse' => $row->warehouse,
                'quantity' => $quantity,
                'average_cost' => $averageCost,
                'stock_value' => $stockValue,
            ];
        }

        return new StockLevelReport($formattedRows, $totalStockValue);
    }
}
