<script setup>
import { ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PlusIcon from '../../Components/icons/PlusIcon.vue';
import PencilIcon from '../../Components/icons/PencilIcon.vue';
import TrashIcon from '../../Components/icons/TrashIcon.vue';
import CheckIcon from '../../Components/icons/CheckIcon.vue';
import { useI18n } from '../../i18n';

const props = defineProps({
    customers: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
});

const { t } = useI18n();
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

const editForms = ref({});

function editForm(customer) {
    if (!editForms.value[customer.id]) {
        editForms.value[customer.id] = useForm({ name: customer.name, phone: customer.phone });
    }
    return editForms.value[customer.id];
}

function submitEditCustomer(customer) {
    editForm(customer).post(`/customers/${customer.id}`, {
        preserveScroll: true,
        onSuccess: () => toggle(null),
    });
}

function toggleStatus(customer) {
    router.post(`/customers/${customer.id}/toggle-status`, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="t('customers.title')">
        <main class="mx-auto max-w-3xl space-y-6 p-6">
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">{{ t('customers.list_title') }}</h2>
                    <button
                        type="button"
                        @click="toggle('customer')"
                        :aria-label="t('common.add')"
                        class="flex size-8 items-center justify-center rounded-full bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </div>

                <form v-if="activePanel === 'customer'" @submit.prevent="submitCustomer" class="mb-4 grid grid-cols-3 gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="customerForm.name" type="text" :placeholder="t('common.name')" class="rounded border-stone-300 text-sm">
                    <input v-model="customerForm.phone" type="text" :placeholder="t('customers.phone_placeholder')" class="rounded border-stone-300 text-sm">
                    <button
                        type="submit"
                        :disabled="customerForm.processing"
                        :aria-label="t('common.add')"
                        class="flex items-center justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </form>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">{{ t('common.name') }}</th><th>{{ t('common.phone') }}</th><th class="text-right">{{ t('customers.balance') }}</th><th>{{ t('common.status') }}</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="c in customers" :key="c.id">
                            <tr class="border-b border-stone-100">
                                <td class="py-2 text-stone-900">{{ c.name }}</td>
                                <td class="text-stone-500">{{ c.phone ?? '—' }}</td>
                                <td class="text-right tabular-nums" :class="parseFloat(c.balance) > 0 ? 'text-amber-700' : ''">{{ money(c.balance) }}</td>
                                <td class="text-stone-500">{{ c.status === 'active' ? t('common.active') : t('common.inactive') }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" @click="toggle(`pay-${c.id}`)" class="mr-2 text-xs text-indigo-700 underline hover:text-indigo-800">{{ t('customers.record_payment') }}</button>
                                    <button type="button" @click="toggle(`edit-${c.id}`)" :aria-label="t('common.edit')" class="mr-1 inline-flex size-7 items-center justify-center rounded-full text-indigo-600 hover:bg-indigo-50">
                                        <PencilIcon class="size-3.5" />
                                    </button>
                                    <button
                                        v-if="c.status === 'active'"
                                        type="button"
                                        @click="toggleStatus(c)"
                                        :aria-label="t('common.deactivate')"
                                        class="inline-flex size-7 items-center justify-center rounded-full text-red-600 hover:bg-red-50"
                                    >
                                        <TrashIcon class="size-3.5" />
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        @click="toggleStatus(c)"
                                        :aria-label="t('common.activate')"
                                        class="inline-flex size-7 items-center justify-center rounded-full text-emerald-600 hover:bg-emerald-50"
                                    >
                                        <CheckIcon class="size-3.5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="activePanel === `pay-${c.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="5" class="p-2">
                                    <div class="flex items-center gap-2">
                                        <input v-model="paymentForm(c.id).amount" type="number" step="0.01" :placeholder="t('customers.amount_placeholder')" class="w-32 rounded border-stone-300 text-sm">
                                        <select v-model="paymentForm(c.id).payment_method_id" class="rounded border-stone-300 text-sm">
                                            <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                                        </select>
                                        <button type="button" @click="recordPayment(c.id)" :disabled="paymentForm(c.id).processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs text-white hover:bg-indigo-700">{{ t('customers.record') }}</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="activePanel === `edit-${c.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="5" class="p-2">
                                    <div class="flex items-center gap-2">
                                        <input v-model="editForm(c).name" type="text" :placeholder="t('common.name')" class="rounded border-stone-300 text-sm">
                                        <input v-model="editForm(c).phone" type="text" :placeholder="t('customers.phone_placeholder')" class="rounded border-stone-300 text-sm">
                                        <button type="button" @click="submitEditCustomer(c)" :disabled="editForm(c).processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs text-white hover:bg-indigo-700">{{ t('common.save') }}</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p v-if="customers.length === 0" class="text-sm text-stone-400">{{ t('customers.none_yet') }}</p>
            </section>
        </main>
    </AppLayout>
</template>
