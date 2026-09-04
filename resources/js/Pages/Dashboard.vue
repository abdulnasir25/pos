<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

defineProps({
    salesSummary: { type: Object, default: null },
    stockLevel: { type: Object, default: null },
    outstandingBalances: { type: Object, default: null },
});

const page = usePage();

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);
}
</script>

<template>
    <AppLayout title="Dashboard">
        <main class="mx-auto max-w-4xl space-y-6 p-6">
            <template v-if="salesSummary">
                <!-- Sales summary (this month) -->
                <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-medium text-stone-900">Sales This Month</h2>
                        <Link href="/reports" class="text-sm text-indigo-700 underline hover:text-indigo-800">Full reports</Link>
                    </div>

                    <div class="grid grid-cols-4 gap-4 text-sm">
                        <div><p class="text-xs text-stone-500">Revenue</p><p class="tabular-nums font-medium">{{ money(salesSummary.revenue) }}</p></div>
                        <div><p class="text-xs text-stone-500">COGS</p><p class="tabular-nums font-medium">{{ money(salesSummary.cogs) }}</p></div>
                        <div><p class="text-xs text-stone-500">Gross Profit</p><p class="tabular-nums font-medium text-emerald-700">{{ money(salesSummary.grossProfit) }}</p></div>
                        <div><p class="text-xs text-stone-500">Sales</p><p class="tabular-nums font-medium">{{ salesSummary.saleCount }}</p></div>
                    </div>

                    <div v-if="salesSummary.byPaymentMethod?.length" class="mt-4 border-t border-stone-200 pt-3">
                        <p class="mb-2 text-xs uppercase text-stone-500">By Payment Method</p>
                        <div class="flex gap-4 text-sm">
                            <span v-for="m in salesSummary.byPaymentMethod" :key="m.method">{{ m.method }}: <span class="font-medium tabular-nums">{{ money(m.amount) }}</span></span>
                        </div>
                    </div>
                </section>

                <!-- Stock value + outstanding balances -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                        <h2 class="mb-3 text-base font-medium text-stone-900">Stock Value</h2>
                        <p class="text-2xl font-semibold tabular-nums text-stone-900">{{ money(stockLevel?.totalStockValue) }}</p>
                        <p class="mt-1 text-xs text-stone-500">Across all warehouses</p>
                    </section>

                    <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                        <h2 class="mb-3 text-base font-medium text-stone-900">Outstanding Balances</h2>
                        <div class="flex justify-between text-sm">
                            <span class="text-stone-600">Customers owe us</span>
                            <span class="font-medium tabular-nums text-amber-700">{{ money(outstandingBalances?.totalReceivable) }}</span>
                        </div>
                        <div class="mt-2 flex justify-between text-sm">
                            <span class="text-stone-600">We owe suppliers</span>
                            <span class="font-medium tabular-nums text-amber-700">{{ money(outstandingBalances?.totalPayable) }}</span>
                        </div>
                    </section>
                </div>
            </template>

            <section v-else class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-1 text-base font-medium text-stone-900">Welcome, {{ page.props.auth.user.name }}</h2>
                <p class="text-sm text-stone-500">Use the menu on the left to get to work.</p>
            </section>
        </main>
    </AppLayout>
</template>
