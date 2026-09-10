<script setup>
import { ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import LandlordLayout from '../../../Layouts/LandlordLayout.vue';

const props = defineProps({
    tenants: { type: Array, default: () => [] },
    plans: { type: Array, default: () => [] },
    subscriptions: { type: Array, default: () => [] },
});

const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

const subscriptionForm = useForm({
    tenant_id: props.tenants[0]?.id ?? null,
    plan_id: props.plans[0]?.id ?? null,
    start_date: new Date().toISOString().slice(0, 10),
});

function submitSubscription() {
    subscriptionForm.post('/landlord/billing/subscriptions', {
        preserveScroll: true,
        onSuccess: () => { subscriptionForm.reset('start_date'); toggle(null); },
    });
}

watch(() => props.tenants, (list) => {
    if (subscriptionForm.tenant_id === null && list.length > 0) subscriptionForm.tenant_id = list[0].id;
});
watch(() => props.plans, (list) => {
    if (subscriptionForm.plan_id === null && list.length > 0) subscriptionForm.plan_id = list[0].id;
});

function generateInvoice(subscriptionId) {
    router.post(`/landlord/billing/subscriptions/${subscriptionId}/invoices`, {}, { preserveScroll: true });
}
</script>

<template>
    <LandlordLayout title="Subscriptions">
        <main class="mx-auto max-w-5xl space-y-6 p-6">
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">Subscriptions</h2>
                    <button type="button" @click="toggle('subscription')" class="text-sm text-indigo-700 underline hover:text-indigo-800">+ Start Subscription</button>
                </div>

                <form v-if="activePanel === 'subscription'" @submit.prevent="submitSubscription" class="mb-4 grid grid-cols-1 gap-2 rounded-md border border-stone-200 p-3 sm:grid-cols-4">
                    <select v-model="subscriptionForm.tenant_id" class="rounded border-stone-300 text-sm">
                        <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                    <select v-model="subscriptionForm.plan_id" class="rounded border-stone-300 text-sm">
                        <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <input v-model="subscriptionForm.start_date" type="date" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="subscriptionForm.processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">Start</button>
                </form>

                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Tenant</th><th>Plan</th><th>Status</th><th>Period</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in subscriptions" :key="s.id" class="border-b border-stone-100">
                            <td class="py-2 text-stone-900">{{ s.tenant }}</td>
                            <td class="text-stone-500">{{ s.plan }}</td>
                            <td class="text-stone-500">{{ s.status }}</td>
                            <td class="text-stone-500">{{ s.current_period_start }} — {{ s.current_period_end }}</td>
                            <td class="text-right">
                                <button type="button" @click="generateInvoice(s.id)" class="text-xs text-indigo-700 underline hover:text-indigo-800">Generate invoice</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <p v-if="subscriptions.length === 0" class="text-sm text-stone-400">No subscriptions yet.</p>
            </section>
        </main>
    </LandlordLayout>
</template>
