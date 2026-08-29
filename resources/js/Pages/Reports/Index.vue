<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    warehouses: { type: Array, default: () => [] },
    financialPeriods: { type: Array, default: () => [] },
    salesSummary: { type: Object, default: null },
    stockLevel: { type: Object, default: null },
    outstandingBalances: { type: Object, default: null },
    profitAndLoss: { type: Object, default: null },
    profitAndLossError: { type: String, default: null },
});


const from = ref(props.filters.from);
const to = ref(props.filters.to);
const warehouseId = ref(props.filters.warehouse_id ?? '');
const financialPeriodId = ref(props.filters.financial_period_id ?? '');

function reload(overrides = {}) {
    router.get('/reports', {
        from: from.value,
        to: to.value,
        warehouse_id: warehouseId.value || undefined,
        financial_period_id: financialPeriodId.value || undefined,
        ...overrides,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);
}
</script>

<template>
    <AppLayout title="Reports">
        <main class="mx-auto max-w-4xl space-y-6 p-6">
            <!-- Sales Summary -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">Sales Summary</h2>

                <div class="mb-4 flex items-end gap-2">
                    <div>
                        <label class="block text-xs text-stone-500">From</label>
                        <input v-model="from" type="date" class="mt-0.5 rounded border-stone-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-stone-500">To</label>
                        <input v-model="to" type="date" class="mt-0.5 rounded border-stone-300 text-sm">
                    </div>
                    <button type="button" @click="reload()" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Run</button>
                </div>

                <div v-if="salesSummary" class="grid grid-cols-4 gap-4 text-sm">
                    <div><p class="text-xs text-stone-500">Revenue</p><p class="tabular-nums font-medium">{{ money(salesSummary.revenue) }}</p></div>
                    <div><p class="text-xs text-stone-500">COGS</p><p class="tabular-nums font-medium">{{ money(salesSummary.cogs) }}</p></div>
                    <div><p class="text-xs text-stone-500">Gross Profit</p><p class="tabular-nums font-medium text-emerald-700">{{ money(salesSummary.grossProfit) }}</p></div>
                    <div><p class="text-xs text-stone-500">Sales</p><p class="tabular-nums font-medium">{{ salesSummary.saleCount }}</p></div>
                </div>

                <div v-if="salesSummary?.byPaymentMethod?.length" class="mt-4 border-t border-stone-200 pt-3">
                    <p class="mb-2 text-xs uppercase text-stone-500">By Payment Method</p>
                    <div class="flex gap-4 text-sm">
                        <span v-for="m in salesSummary.byPaymentMethod" :key="m.method">{{ m.method }}: <span class="font-medium tabular-nums">{{ money(m.amount) }}</span></span>
                    </div>
                </div>
            </section>

            <!-- Stock Level -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-end justify-between">
                    <h2 class="text-base font-medium text-stone-900">Stock Level</h2>
                    <div class="flex items-end gap-2">
                        <select v-model="warehouseId" class="rounded border-stone-300 text-sm">
                            <option value="">All warehouses</option>
                            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                        <button type="button" @click="reload()" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Run</button>
                    </div>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Product</th><th>Warehouse</th><th>Qty</th><th>Avg Cost</th><th class="text-right">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in stockLevel?.rows ?? []" :key="i" class="border-b border-stone-100">
                            <td class="py-2">{{ row.product }}</td>
                            <td class="text-stone-500">{{ row.warehouse }}</td>
                            <td class="tabular-nums">{{ row.quantity }}</td>
                            <td class="tabular-nums">{{ money(row.average_cost) }}</td>
                            <td class="text-right tabular-nums">{{ money(row.stock_value) }}</td>
                        </tr>
                        <tr v-if="!stockLevel?.rows?.length"><td colspan="5" class="py-3 text-center text-stone-400">No stock on hand.</td></tr>
                    </tbody>
                    <tfoot v-if="stockLevel?.rows?.length">
                        <tr class="border-t border-stone-200 font-medium">
                            <td colspan="4" class="py-2">Total stock value</td>
                            <td class="text-right tabular-nums">{{ money(stockLevel.totalStockValue) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            <!-- Profit and Loss -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-end justify-between">
                    <h2 class="text-base font-medium text-stone-900">Profit &amp; Loss</h2>
                    <div class="flex items-end gap-2">
                        <select v-model="financialPeriodId" class="rounded border-stone-300 text-sm">
                            <option value="">Select a period</option>
                            <option v-for="p in financialPeriods" :key="p.id" :value="p.id">{{ p.label }}</option>
                        </select>
                        <button type="button" @click="reload()" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Run</button>
                    </div>
                </div>

                <p v-if="profitAndLossError" class="text-sm text-amber-700">{{ profitAndLossError }}</p>
                <p v-else-if="!profitAndLoss" class="text-sm text-stone-400">Select a financial period above.</p>
                <div v-else class="grid grid-cols-3 gap-3 text-sm">
                    <div><p class="text-xs text-stone-500">Gross Profit</p><p class="tabular-nums font-medium">{{ money(profitAndLoss.grossProfit) }}</p></div>
                    <div><p class="text-xs text-stone-500">Salary Expense</p><p class="tabular-nums font-medium">{{ money(profitAndLoss.salaryExpense) }}</p></div>
                    <div><p class="text-xs text-stone-500">Commission Expense</p><p class="tabular-nums font-medium">{{ money(profitAndLoss.commissionExpense) }}</p></div>
                    <div><p class="text-xs text-stone-500">Other Expenses</p><p class="tabular-nums font-medium">{{ money(profitAndLoss.otherOperatingExpenses) }}</p></div>
                    <div><p class="text-xs text-stone-500">Net Profit</p><p class="tabular-nums font-medium text-emerald-700">{{ money(profitAndLoss.netProfit) }}</p></div>
                    <div><p class="text-xs text-stone-500">Status</p><p class="font-medium">{{ profitAndLoss.status }}</p></div>
                    <div><p class="text-xs text-stone-500">Distributable</p><p class="tabular-nums font-medium">{{ money(profitAndLoss.distributableProfit) }}</p></div>
                    <div><p class="text-xs text-stone-500">Retained</p><p class="tabular-nums font-medium">{{ money(profitAndLoss.retainedProfit) }}</p></div>
                </div>
            </section>

            <!-- Outstanding Balances -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">Outstanding Balances</h2>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="mb-2 text-xs uppercase text-stone-500">Customers owe us — {{ money(outstandingBalances?.totalReceivable) }}</p>
                        <div v-for="(c, i) in outstandingBalances?.customers ?? []" :key="i" class="flex justify-between border-b border-stone-100 py-1 text-sm">
                            <span>{{ c.name }}</span><span class="tabular-nums">{{ money(c.balance) }}</span>
                        </div>
                        <p v-if="!outstandingBalances?.customers?.length" class="text-sm text-stone-400">None outstanding.</p>
                    </div>
                    <div>
                        <p class="mb-2 text-xs uppercase text-stone-500">We owe suppliers — {{ money(outstandingBalances?.totalPayable) }}</p>
                        <div v-for="(s, i) in outstandingBalances?.suppliers ?? []" :key="i" class="flex justify-between border-b border-stone-100 py-1 text-sm">
                            <span>{{ s.name }}</span><span class="tabular-nums">{{ money(s.balance) }}</span>
                        </div>
                        <p v-if="!outstandingBalances?.suppliers?.length" class="text-sm text-stone-400">None outstanding.</p>
                    </div>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
