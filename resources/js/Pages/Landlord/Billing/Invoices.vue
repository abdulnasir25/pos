<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LandlordLayout from '../../../Layouts/LandlordLayout.vue';

defineProps({
    invoices: { type: Array, default: () => [] },
});

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);
}

const paymentForms = ref({});

function paymentForm(invoiceId) {
    if (!paymentForms.value[invoiceId]) {
        paymentForms.value[invoiceId] = useForm({ paid_at: new Date().toISOString().slice(0, 10) });
    }
    return paymentForms.value[invoiceId];
}

function recordPayment(invoiceId) {
    paymentForm(invoiceId).post(`/landlord/billing/invoices/${invoiceId}/pay`, { preserveScroll: true });
}
</script>

<template>
    <LandlordLayout title="Invoices">
        <main class="mx-auto max-w-5xl space-y-6 p-6">
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">Invoices</h2>
                <div v-for="inv in invoices" :key="inv.id" class="mb-2 flex flex-col gap-2 rounded-md border border-stone-200 p-2 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <span class="font-medium text-stone-900">{{ inv.tenant }}</span>
                        <span class="ml-2 text-stone-500">{{ money(inv.amount) }} · due {{ inv.due_date }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="rounded-full px-2 py-0.5 text-xs"
                            :class="inv.status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                        >{{ inv.status }}</span>
                        <template v-if="inv.status !== 'paid'">
                            <input v-model="paymentForm(inv.id).paid_at" type="date" class="rounded border-stone-300 text-xs">
                            <button type="button" @click="recordPayment(inv.id)" class="text-xs text-indigo-700 underline hover:text-indigo-800">Record payment</button>
                        </template>
                        <span v-else class="text-xs text-stone-400">paid {{ inv.paid_at }}</span>
                    </div>
                </div>
                <p v-if="invoices.length === 0" class="text-sm text-stone-400">No invoices yet.</p>
            </section>
        </main>
    </LandlordLayout>
</template>
