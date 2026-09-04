<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useI18n } from '../../i18n';

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

const { t } = useI18n();

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
    <AppLayout :title="t('reports.title')">
        <main class="mx-auto max-w-4xl space-y-6 p-6">
            <!-- Sales Summary -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">{{ t('reports.sales_summary') }}</h2>

                <div class="mb-4 flex items-end gap-2">
                    <div>
                        <label class="block text-xs text-stone-500">{{ t('reports.from') }}</label>
                        <input v-model="from" type="date" class="mt-0.5 rounded border-stone-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-stone-500">{{ t('reports.to') }}</label>
                        <input v-model="to" type="date" class="mt-0.5 rounded border-stone-300 text-sm">
                    </div>
                    <button type="button" @click="reload()" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">{{ t('reports.run') }}</button>
                </div>

                <div v-if="salesSummary" class="grid grid-cols-4 gap-4 text-sm">
                    <div><p class="text-xs text-stone-500">{{ t('dashboard.revenue') }}</p><p class="tabular-nums font-medium">{{ money(salesSummary.revenue) }}</p></div>
                    <div><p class="text-xs text-stone-500">{{ t('dashboard.cogs') }}</p><p class="tabular-nums font-medium">{{ money(salesSummary.cogs) }}</p></div>
                    <div><p class="text-xs text-stone-500">{{ t('dashboard.gross_profit') }}</p><p class="tabular-nums font-medium text-emerald-700">{{ money(salesSummary.grossProfit) }}</p></div>
                    <div><p class="text-xs text-stone-500">{{ t('dashboard.sales_count') }}</p><p class="tabular-nums font-medium">{{ salesSummary.saleCount }}</p></div>
                </div>

                <div v-if="salesSummary?.byPaymentMethod?.length" class="mt-4 border-t border-stone-200 pt-3">
                    <p class="mb-2 text-xs uppercase text-stone-500">{{ t('reports.by_payment_method') }}</p>
                    <div class="flex gap-4 text-sm">
                        <span v-for="m in salesSummary.byPaymentMethod" :key="m.method">{{ m.method }}: <span class="font-medium tabular-nums">{{ money(m.amount) }}</span></span>
                    </div>
                </div>
            </section>

            <!-- Stock Level -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-end justify-between">
                    <h2 class="text-base font-medium text-stone-900">{{ t('reports.stock_level') }}</h2>
                    <div class="flex items-end gap-2">
                        <select v-model="warehouseId" class="rounded border-stone-300 text-sm">
                            <option value="">{{ t('reports.all_warehouses') }}</option>
                            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                        <button type="button" @click="reload()" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">{{ t('reports.run') }}</button>
                    </div>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">{{ t('reports.product') }}</th><th>{{ t('reports.warehouse') }}</th><th>{{ t('reports.qty') }}</th><th>{{ t('reports.avg_cost') }}</th><th class="text-right">{{ t('reports.value') }}</th>
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
                        <tr v-if="!stockLevel?.rows?.length"><td colspan="5" class="py-3 text-center text-stone-400">{{ t('reports.no_stock') }}</td></tr>
                    </tbody>
                    <tfoot v-if="stockLevel?.rows?.length">
                        <tr class="border-t border-stone-200 font-medium">
                            <td colspan="4" class="py-2">{{ t('reports.total_stock_value') }}</td>
                            <td class="text-right tabular-nums">{{ money(stockLevel.totalStockValue) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            <!-- Profit and Loss -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-end justify-between">
                    <h2 class="text-base font-medium text-stone-900">{{ t('reports.profit_and_loss') }}</h2>
                    <div class="flex items-end gap-2">
                        <select v-model="financialPeriodId" class="rounded border-stone-300 text-sm">
                            <option value="">{{ t('reports.select_period') }}</option>
                            <option v-for="p in financialPeriods" :key="p.id" :value="p.id">{{ p.label }}</option>
                        </select>
                        <button type="button" @click="reload()" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">{{ t('reports.run') }}</button>
                    </div>
                </div>

                <p v-if="profitAndLossError" class="text-sm text-amber-700">{{ profitAndLossError }}</p>
                <p v-else-if="!profitAndLoss" class="text-sm text-stone-400">{{ t('reports.select_period_hint') }}</p>
                <div v-else class="grid grid-cols-3 gap-3 text-sm">
                    <div><p class="text-xs text-stone-500">{{ t('reports.gross_profit') }}</p><p class="tabular-nums font-medium">{{ money(profitAndLoss.grossProfit) }}</p></div>
                    <div><p class="text-xs text-stone-500">{{ t('reports.salary_expense') }}</p><p class="tabular-nums font-medium">{{ money(profitAndLoss.salaryExpense) }}</p></div>
                    <div><p class="text-xs text-stone-500">{{ t('reports.commission_expense') }}</p><p class="tabular-nums font-medium">{{ money(profitAndLoss.commissionExpense) }}</p></div>
                    <div><p class="text-xs text-stone-500">{{ t('reports.other_expenses') }}</p><p class="tabular-nums font-medium">{{ money(profitAndLoss.otherOperatingExpenses) }}</p></div>
                    <div><p class="text-xs text-stone-500">{{ t('reports.net_profit') }}</p><p class="tabular-nums font-medium text-emerald-700">{{ money(profitAndLoss.netProfit) }}</p></div>
                    <div><p class="text-xs text-stone-500">{{ t('reports.status') }}</p><p class="font-medium">{{ profitAndLoss.status }}</p></div>
                    <div><p class="text-xs text-stone-500">{{ t('reports.distributable') }}</p><p class="tabular-nums font-medium">{{ money(profitAndLoss.distributableProfit) }}</p></div>
                    <div><p class="text-xs text-stone-500">{{ t('reports.retained') }}</p><p class="tabular-nums font-medium">{{ money(profitAndLoss.retainedProfit) }}</p></div>
                </div>
            </section>

            <!-- Outstanding Balances -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">{{ t('reports.outstanding_balances') }}</h2>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="mb-2 text-xs uppercase text-stone-500">{{ t('reports.customers_owe', { amount: money(outstandingBalances?.totalReceivable) }) }}</p>
                        <div v-for="(c, i) in outstandingBalances?.customers ?? []" :key="i" class="flex justify-between border-b border-stone-100 py-1 text-sm">
                            <span>{{ c.name }}</span><span class="tabular-nums">{{ money(c.balance) }}</span>
                        </div>
                        <p v-if="!outstandingBalances?.customers?.length" class="text-sm text-stone-400">{{ t('reports.none_outstanding') }}</p>
                    </div>
                    <div>
                        <p class="mb-2 text-xs uppercase text-stone-500">{{ t('reports.we_owe', { amount: money(outstandingBalances?.totalPayable) }) }}</p>
                        <div v-for="(s, i) in outstandingBalances?.suppliers ?? []" :key="i" class="flex justify-between border-b border-stone-100 py-1 text-sm">
                            <span>{{ s.name }}</span><span class="tabular-nums">{{ money(s.balance) }}</span>
                        </div>
                        <p v-if="!outstandingBalances?.suppliers?.length" class="text-sm text-stone-400">{{ t('reports.none_outstanding') }}</p>
                    </div>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
