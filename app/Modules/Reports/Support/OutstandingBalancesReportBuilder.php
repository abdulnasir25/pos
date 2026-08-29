<?php

namespace App\Modules\Reports\Support;

use App\Modules\Customers\Models\Customer;
use App\Modules\Reports\DTOs\OutstandingBalancesReport;
use App\Modules\Suppliers\Models\Supplier;

class OutstandingBalancesReportBuilder
{
    public function build(): OutstandingBalancesReport
    {
        $customers = Customer::where('balance', '>', 0)
            ->orderByDesc('balance')
            ->get(['name', 'balance'])
            ->map(fn ($c) => ['name' => $c->name, 'balance' => (string) $c->balance])
            ->all();

        $suppliers = Supplier::where('balance', '>', 0)
            ->orderByDesc('balance')
            ->get(['name', 'balance'])
            ->map(fn ($s) => ['name' => $s->name, 'balance' => (string) $s->balance])
            ->all();

        $totalReceivable = array_reduce($customers, fn ($carry, $c) => bcadd($carry, $c['balance'], 2), '0.00');
        $totalPayable = array_reduce($suppliers, fn ($carry, $s) => bcadd($carry, $s['balance'], 2), '0.00');

        return new OutstandingBalancesReport($customers, $totalReceivable, $suppliers, $totalPayable);
    }
}
