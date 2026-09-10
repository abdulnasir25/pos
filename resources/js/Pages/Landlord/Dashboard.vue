<script setup>
import { Link } from '@inertiajs/vue3';
import LandlordLayout from '../../Layouts/LandlordLayout.vue';

const props = defineProps({
    stats: { type: Object, required: true },
    overdueInvoices: { type: Array, default: () => [] },
    recentTenants: { type: Array, default: () => [] },
});

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);
}
</script>

<template>
    <LandlordLayout title="Dashboard">
        <main class="mx-auto max-w-5xl space-y-6 p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-stone-200/70 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-stone-400">Shops</p>
                    <p class="mt-1 text-2xl font-semibold text-stone-900">{{ stats.tenants_total }}</p>
                    <p class="mt-1 text-xs text-stone-500">{{ stats.tenants_active }} active · {{ stats.tenants_suspended }} suspended</p>
                </div>
                <div class="rounded-xl border border-stone-200/70 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-stone-400">Monthly Recurring Revenue</p>
                    <p class="mt-1 text-2xl font-semibold text-stone-900">{{ money(stats.mrr) }}</p>
                    <p class="mt-1 text-xs text-stone-500">From active subscriptions</p>
                </div>
                <div class="rounded-xl border border-stone-200/70 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-stone-400">Overdue</p>
                    <p class="mt-1 text-2xl font-semibold" :class="stats.overdue_count > 0 ? 'text-red-600' : 'text-stone-900'">{{ stats.overdue_count }}</p>
                    <p class="mt-1 text-xs text-stone-500">{{ money(stats.overdue_amount) }} outstanding</p>
                </div>
                <div class="rounded-xl border border-stone-200/70 bg-white p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-stone-400">Collected This Month</p>
                    <p class="mt-1 text-2xl font-semibold text-stone-900">{{ money(stats.collected_this_month) }}</p>
                    <p class="mt-1 text-xs text-stone-500">Invoices paid so far</p>
                </div>
            </div>

            <section v-if="overdueInvoices.length > 0" class="rounded-xl border border-red-200 bg-red-50/40 p-6 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">Overdue Invoices</h2>
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Tenant</th><th>Due</th><th class="text-right">Amount</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="inv in overdueInvoices" :key="inv.id" class="border-b border-stone-100">
                            <td class="py-2 text-stone-900">{{ inv.tenant }}</td>
                            <td class="text-red-700">{{ inv.due_date }}</td>
                            <td class="text-right tabular-nums">{{ money(inv.amount) }}</td>
                            <td class="text-right">
                                <Link :href="`/landlord/billing/invoices/${inv.id}`" class="text-xs text-indigo-700 underline hover:text-indigo-800">View</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </section>

            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">Recently Added Shops</h2>
                    <Link href="/landlord/tenants" class="text-xs text-indigo-700 underline hover:text-indigo-800">View all</Link>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Name</th><th>Subdomain</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in recentTenants" :key="t.id" class="border-b border-stone-100">
                            <td class="py-2 text-stone-900">{{ t.name }}</td>
                            <td class="text-stone-500">{{ t.slug }}</td>
                            <td>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="t.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'"
                                >{{ t.status }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <p v-if="recentTenants.length === 0" class="text-sm text-stone-400">No shops yet.</p>
            </section>
        </main>
    </LandlordLayout>
</template>
