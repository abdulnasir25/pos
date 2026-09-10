<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useI18n } from '../../i18n';

defineProps({
    sales: { type: Array, default: () => [] },
});

const { t } = useI18n();

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);
}

const statusClass = {
    confirmed: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-red-100 text-red-800',
    refunded: 'bg-amber-100 text-amber-800',
};

function statusLabel(status) {
    return t(`sales.status_${status}`);
}
</script>

<template>
    <AppLayout :title="t('sales.title')">
        <main class="mx-auto max-w-4xl space-y-6 p-6">
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">{{ t('sales.list_title') }}</h2>

                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">{{ t('sales.bill_no') }}</th><th>{{ t('sales.date') }}</th><th>{{ t('sales.customer') }}</th><th class="text-right">{{ t('sales.total') }}</th><th>{{ t('common.status') }}</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in sales" :key="s.id" class="border-b border-stone-100">
                            <td class="py-2 font-medium text-stone-900">{{ s.reference_no }}</td>
                            <td class="text-stone-500">{{ s.confirmed_at }}</td>
                            <td class="text-stone-500">{{ s.customer ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ money(s.total) }}</td>
                            <td>
                                <span class="rounded-full px-2 py-0.5 text-xs" :class="statusClass[s.status]">{{ statusLabel(s.status) }}</span>
                            </td>
                            <td class="text-right">
                                <Link :href="`/sales/${s.id}`" class="text-xs text-indigo-700 underline hover:text-indigo-800">{{ t('sales.view') }}</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <p v-if="sales.length === 0" class="text-sm text-stone-400">{{ t('sales.none_yet') }}</p>
            </section>
        </main>
    </AppLayout>
</template>
