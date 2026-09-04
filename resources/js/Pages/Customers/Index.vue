<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    customers: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
});

const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);
}

const customerForm = useForm({ name: '', phone: '' });

function submitCustomer() {
    customerForm.post('/customers', { preserveScroll: true, onSuccess: () => customerForm.reset() });
}

const paymentForms = ref({});

function paymentForm(customerId) {
    if (!paymentForms.value[customerId]) {
        paymentForms.value[customerId] = useForm({ amount: '', payment_method_id: props.paymentMethods[0]?.id ?? null });
    }
    return paymentForms.value[customerId];
}

watch(() => props.paymentMethods, (list) => {
    if (list.length === 0) return;
    Object.values(paymentForms.value).forEach((f) => {
        if (f.payment_method_id === null) f.payment_method_id = list[0].id;
    });
});

function recordPayment(customerId) {
    paymentForm(customerId).post(`/customers/${customerId}/payments`, {
        preserveScroll: true,
        onSuccess: () => { paymentForm(customerId).reset('amount'); toggle(null); },
    });
}
</script>

<template>
    <AppLayout title="Customers">
        <main class="mx-auto max-w-3xl space-y-6 p-6">
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">Customers</h2>
                    <button type="button" @click="toggle('customer')" class="text-sm text-indigo-700 underline hover:text-indigo-800">+ Add Customer</button>
                </div>

                <form v-if="activePanel === 'customer'" @submit.prevent="submitCustomer" class="mb-4 grid grid-cols-3 gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="customerForm.name" type="text" placeholder="Name" class="rounded border-stone-300 text-sm">
                    <input v-model="customerForm.phone" type="text" placeholder="Phone (optional)" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="customerForm.processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">Add</button>
                </form>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Name</th><th>Phone</th><th class="text-right">Balance</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="c in customers" :key="c.id">
                            <tr class="border-b border-stone-100">
                                <td class="py-2 text-stone-900">{{ c.name }}</td>
                                <td class="text-stone-500">{{ c.phone ?? '—' }}</td>
                                <td class="text-right tabular-nums" :class="parseFloat(c.balance) > 0 ? 'text-amber-700' : ''">{{ money(c.balance) }}</td>
                                <td class="text-stone-500">{{ c.status }}</td>
                                <td class="text-right">
                                    <button type="button" @click="toggle(`pay-${c.id}`)" class="text-xs text-indigo-700 underline hover:text-indigo-800">Record payment</button>
                                </td>
                            </tr>
                            <tr v-if="activePanel === `pay-${c.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="5" class="p-2">
                                    <div class="flex items-center gap-2">
                                        <input v-model="paymentForm(c.id).amount" type="number" step="0.01" placeholder="Amount" class="w-32 rounded border-stone-300 text-sm">
                                        <select v-model="paymentForm(c.id).payment_method_id" class="rounded border-stone-300 text-sm">
                                            <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                                        </select>
                                        <button type="button" @click="recordPayment(c.id)" :disabled="paymentForm(c.id).processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs text-white hover:bg-indigo-700">Record</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p v-if="customers.length === 0" class="text-sm text-stone-400">No customers yet.</p>
            </section>
        </main>
    </AppLayout>
</template>
