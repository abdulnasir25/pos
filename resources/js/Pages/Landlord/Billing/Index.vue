<script setup>
import { ref, watch } from 'vue';
import { usePage, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    tenants: { type: Array, default: () => [] },
    plans: { type: Array, default: () => [] },
    subscriptions: { type: Array, default: () => [] },
    invoices: { type: Array, default: () => [] },
});

const page = usePage();
const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);
}

function logout() {
    router.post('/landlord/logout');
}

// --- Plans -----------------------------------------------------------

const planForm = useForm({ name: '', slug: '', price: '', billing_interval: 'monthly' });

function submitPlan() {
    planForm.post('/landlord/billing/plans', {
        preserveScroll: true,
        onSuccess: () => planForm.reset(),
    });
}

// --- Subscriptions -----------------------------------------------------

const subscriptionForm = useForm({
    tenant_id: props.tenants[0]?.id ?? null,
    plan_id: props.plans[0]?.id ?? null,
    start_date: new Date().toISOString().slice(0, 10),
});

function submitSubscription() {
    subscriptionForm.post('/landlord/billing/subscriptions', {
        preserveScroll: true,
        onSuccess: () => subscriptionForm.reset('start_date'),
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

// --- Invoices ------------------------------------------------------------

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
    <div class="min-h-screen bg-stone-100">
        <header class="flex items-center justify-between border-b border-stone-200 bg-white px-6 py-4">
            <h1 class="text-lg font-semibold text-stone-900">SaaS Billing</h1>
            <div class="flex items-center gap-4 text-sm">
                <span class="text-stone-500">{{ page.props.auth.user?.name }}</span>
                <button type="button" @click="logout" class="text-stone-600 underline">Sign out</button>
            </div>
        </header>

        <div v-if="page.props.flash?.success" class="mx-6 mt-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ page.props.flash.success }}
        </div>
        <div v-if="Object.keys(page.props.errors ?? {}).length" class="mx-6 mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p v-for="(msg, key) in page.props.errors" :key="key">{{ msg }}</p>
        </div>

        <main class="mx-auto max-w-5xl space-y-6 p-6">
            <!-- Tenants -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">Tenants</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Name</th><th>Slug</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in tenants" :key="t.id" class="border-b border-stone-100">
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
                <p v-if="tenants.length === 0" class="text-sm text-stone-400">No tenants yet.</p>
            </section>

            <!-- Plans -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">Plans</h2>
                    <button type="button" @click="toggle('plan')" class="text-sm text-stone-600 underline">+ Add Plan</button>
                </div>

                <form v-if="activePanel === 'plan'" @submit.prevent="submitPlan" class="mb-4 grid grid-cols-4 gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="planForm.name" type="text" placeholder="Name" class="rounded border-stone-300 text-sm">
                    <input v-model="planForm.slug" type="text" placeholder="Slug (e.g. pro)" class="rounded border-stone-300 text-sm">
                    <input v-model="planForm.price" type="number" step="0.01" placeholder="Price" class="rounded border-stone-300 text-sm">
                    <select v-model="planForm.billing_interval" class="rounded border-stone-300 text-sm">
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                    <button type="submit" :disabled="planForm.processing" class="col-span-4 rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Add</button>
                </form>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Name</th><th>Slug</th><th>Interval</th><th class="text-right">Price</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in plans" :key="p.id" class="border-b border-stone-100">
                            <td class="py-2 text-stone-900">{{ p.name }}</td>
                            <td class="text-stone-500">{{ p.slug }}</td>
                            <td class="text-stone-500">{{ p.billing_interval }}</td>
                            <td class="text-right tabular-nums">{{ money(p.price) }}</td>
                            <td class="text-stone-500">{{ p.status }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="plans.length === 0" class="text-sm text-stone-400">No plans yet.</p>
            </section>

            <!-- Subscriptions -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">Subscriptions</h2>
                    <button type="button" @click="toggle('subscription')" class="text-sm text-stone-600 underline">+ Start Subscription</button>
                </div>

                <form v-if="activePanel === 'subscription'" @submit.prevent="submitSubscription" class="mb-4 grid grid-cols-4 gap-2 rounded-md border border-stone-200 p-3">
                    <select v-model="subscriptionForm.tenant_id" class="rounded border-stone-300 text-sm">
                        <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                    <select v-model="subscriptionForm.plan_id" class="rounded border-stone-300 text-sm">
                        <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <input v-model="subscriptionForm.start_date" type="date" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="subscriptionForm.processing" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Start</button>
                </form>

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
                                <button type="button" @click="generateInvoice(s.id)" class="text-xs text-stone-600 underline">Generate invoice</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="subscriptions.length === 0" class="text-sm text-stone-400">No subscriptions yet.</p>
            </section>

            <!-- Invoices -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">Invoices</h2>
                <div v-for="inv in invoices" :key="inv.id" class="mb-2 flex items-center justify-between rounded-md border border-stone-200 p-2 text-sm">
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
                            <button type="button" @click="recordPayment(inv.id)" class="text-xs text-stone-600 underline">Record payment</button>
                        </template>
                        <span v-else class="text-xs text-stone-400">paid {{ inv.paid_at }}</span>
                    </div>
                </div>
                <p v-if="invoices.length === 0" class="text-sm text-stone-400">No invoices yet.</p>
            </section>
        </main>
    </div>
</template>
