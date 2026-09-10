<script setup>
import { computed, reactive } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import TrashIcon from '../../Components/icons/TrashIcon.vue';
import { useI18n } from '../../i18n';

const props = defineProps({
    sale: { type: Object, required: true },
    shop: { type: Object, required: true },
    receipt: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    returns: { type: Array, default: () => [] },
});

const { t } = useI18n();
const page = usePage();

function money(n) {
    const amount = (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);

    return props.shop.currency_symbol ? `${props.shop.currency_symbol} ${amount}` : amount;
}

const permissions = computed(() => page.props.auth.user?.permissions ?? []);
const canCancel = computed(() => permissions.value.includes('sales.cancel'));
const canReturn = computed(() => permissions.value.includes('sales.return'));

const statusClass = {
    confirmed: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-red-100 text-red-800',
    refunded: 'bg-amber-100 text-amber-800',
};

function statusLabel(status) {
    return t(`sales.status_${status}`);
}

function cancelSale() {
    router.post(`/sales/${props.sale.id}/cancel`, {}, { preserveScroll: true });
}

// --- Return -----------------------------------------------------------

const returnQuantities = reactive({});
const returnableItems = computed(() => props.items.filter((i) => parseFloat(i.eligible_for_return) > 0));
const hasReturnableItems = computed(() => returnableItems.value.length > 0);

const returnForm = useForm({ notes: '' });

function submitReturn() {
    const lines = props.items
        .filter((i) => parseFloat(returnQuantities[i.id]) > 0)
        .map((i) => ({ sale_item_id: i.id, quantity: returnQuantities[i.id] }));

    if (lines.length === 0) return;

    returnForm.transform((data) => ({ ...data, lines })).post(`/sales/${props.sale.id}/returns`, {
        preserveScroll: true,
        onSuccess: () => { returnForm.reset(); Object.keys(returnQuantities).forEach((k) => delete returnQuantities[k]); },
    });
}
</script>

<template>
    <AppLayout :title="receipt.reference_no">
        <main class="mx-auto max-w-3xl space-y-6 p-6">
            <Link href="/sales" class="text-sm text-indigo-700 underline hover:text-indigo-800">{{ t('sales.back') }}</Link>

            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-4 border-b border-stone-100 pb-4 text-center" dir="auto">
                    <p class="text-base font-semibold text-stone-900">{{ shop.name }}</p>
                    <p v-if="shop.address" class="text-xs text-stone-500">{{ shop.address }}</p>
                    <p v-if="shop.phone" class="text-xs text-stone-500">{{ shop.phone }}</p>
                </div>

                <div class="mb-4 flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-stone-900">{{ receipt.reference_no }}</h2>
                        <p class="text-sm text-stone-500">{{ receipt.issued_at }} · {{ receipt.customer_name }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass[sale.status]">{{ statusLabel(sale.status) }}</span>
                        <button
                            v-if="sale.status === 'confirmed' && canCancel"
                            type="button"
                            @click="cancelSale"
                            :aria-label="t('sales.cancel_sale')"
                            class="inline-flex size-8 items-center justify-center rounded-full text-red-600 hover:bg-red-50"
                        >
                            <TrashIcon class="size-4" />
                        </button>
                    </div>
                </div>

                <p v-if="sale.status === 'cancelled'" class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700" dir="auto">
                    {{ t('sales.cancelled_note', { date: sale.cancelled_at }) }}
                </p>

                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">{{ t('sales.items') }}</th><th>{{ t('sales.unit') }}</th><th class="text-right">{{ t('sales.qty') }}</th><th class="text-right">{{ t('sales.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items" :key="item.id" class="border-b border-stone-100">
                            <td class="py-2 text-stone-900">{{ item.product }}</td>
                            <td class="text-stone-500">{{ item.unit }}</td>
                            <td class="text-right tabular-nums">{{ item.quantity }}</td>
                            <td class="text-right tabular-nums">{{ money(item.line_total) }}</td>
                        </tr>
                    </tbody>
                </table>
                </div>

                <div class="mt-4 space-y-1 border-t border-stone-200 pt-3 text-sm">
                    <div class="flex justify-between text-stone-600"><span>{{ t('sales.subtotal') }}</span><span class="tabular-nums">{{ money(receipt.subtotal) }}</span></div>
                    <div v-if="parseFloat(receipt.discount_total) > 0" class="flex justify-between text-stone-600"><span>{{ t('pos.discount') }}</span><span class="tabular-nums">{{ money(receipt.discount_total) }}</span></div>
                    <div class="flex justify-between font-medium text-stone-900"><span>{{ t('sales.grand_total') }}</span><span class="tabular-nums">{{ money(receipt.total) }}</span></div>
                    <div class="flex justify-between text-stone-600"><span>{{ t('sales.paid') }}</span><span class="tabular-nums">{{ money(receipt.paid_total) }}</span></div>
                    <div class="flex justify-between text-stone-600"><span>{{ t('sales.balance_due') }}</span><span class="tabular-nums">{{ money(receipt.balance_due) }}</span></div>
                </div>

                <div class="mt-4 border-t border-stone-200 pt-3">
                    <p class="mb-1 text-xs uppercase tracking-wide text-stone-400">{{ t('sales.payments') }}</p>
                    <div v-for="(p, i) in receipt.payments" :key="i" class="flex justify-between text-sm text-stone-700">
                        <span>{{ p.method }}</span><span class="tabular-nums">{{ money(p.amount) }}</span>
                    </div>
                </div>

                <p v-if="shop.receipt_footer" class="mt-4 border-t border-stone-100 pt-3 text-center text-xs text-stone-500" dir="auto">
                    {{ shop.receipt_footer }}
                </p>
            </section>

            <section v-if="returns.length > 0" class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">{{ t('sales.returns_title') }}</h2>
                <div v-for="r in returns" :key="r.id" class="mb-2 rounded-md border border-stone-200 p-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-stone-500">{{ r.created_at }}</span>
                        <span class="font-medium tabular-nums text-stone-900">{{ t('sales.refund_amount') }}: {{ money(r.refund_amount) }}</span>
                    </div>
                    <p v-if="r.notes" class="mt-1 text-xs text-stone-500" dir="auto">{{ r.notes }}</p>
                </div>
            </section>

            <section v-if="sale.status === 'confirmed' && canReturn" class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-1 text-base font-medium text-stone-900">{{ t('sales.return_title') }}</h2>
                <p v-if="hasReturnableItems" class="mb-3 text-xs text-stone-500">{{ t('sales.return_hint') }}</p>
                <p v-else class="text-sm text-stone-400">{{ t('sales.fully_returned_note') }}</p>

                <form v-if="hasReturnableItems" @submit.prevent="submitReturn" class="space-y-3">
                    <div v-for="item in returnableItems" :key="item.id" class="flex items-center gap-3 text-sm">
                        <span class="flex-1 text-stone-900">{{ item.product }}</span>
                        <span class="text-xs text-stone-400">{{ t('sales.eligible_for_return', { qty: item.eligible_for_return }) }}</span>
                        <input
                            v-model="returnQuantities[item.id]"
                            type="number"
                            step="0.0001"
                            min="0"
                            :max="item.eligible_for_return"
                            :placeholder="t('sales.return_qty_placeholder')"
                            class="w-28 rounded border-stone-300 text-sm"
                        >
                    </div>
                    <input v-model="returnForm.notes" type="text" :placeholder="t('sales.notes_placeholder')" class="w-full rounded border-stone-300 text-sm" dir="auto">
                    <button type="submit" :disabled="returnForm.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">{{ t('sales.process_return') }}</button>
                </form>
            </section>
        </main>
    </AppLayout>
</template>
