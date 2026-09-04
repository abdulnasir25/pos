<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    recentSessions: { type: Array, default: () => [] },
});

const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);
}

// --- Add account -----------------------------------------------------------

const accountForm = useForm({ name: '', account_type: 'cash', opening_balance: '0.00' });

function submitAccount() {
    accountForm.post('/cash-register/accounts', { preserveScroll: true, onSuccess: () => accountForm.reset('name') });
}

// --- Open session --------------------------------------------------------

const closedCashAccounts = computed(() => props.accounts.filter((a) => a.account_type === 'cash' && !a.open_session));

const openForm = useForm({
    financial_account_id: closedCashAccounts.value[0]?.id ?? null,
    opening_float: '',
});

watch(() => props.accounts, () => {
    if (openForm.financial_account_id === null && closedCashAccounts.value.length > 0) {
        openForm.financial_account_id = closedCashAccounts.value[0].id;
    }
});

function submitOpen() {
    openForm.post('/cash-register/sessions', { preserveScroll: true, onSuccess: () => openForm.reset('opening_float') });
}

// --- Close session ---------------------------------------------------------

const openAccounts = computed(() => props.accounts.filter((a) => a.open_session));

function closeSession(sessionId, countedClosing) {
    router.post(`/cash-register/sessions/${sessionId}/close`, { counted_closing: countedClosing }, { preserveScroll: true });
}

const closingAmounts = ref({});
</script>

<template>
    <AppLayout title="Cash Register">
        <main class="mx-auto max-w-3xl space-y-6 p-6">
            <!-- Accounts -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">Financial Accounts</h2>
                    <button type="button" @click="toggle('account')" class="text-sm text-indigo-700 underline hover:text-indigo-800">+ Add Account</button>
                </div>

                <form v-if="activePanel === 'account'" @submit.prevent="submitAccount" class="mb-4 grid grid-cols-3 gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="accountForm.name" type="text" placeholder="Name (e.g. Main Till)" class="rounded border-stone-300 text-sm">
                    <select v-model="accountForm.account_type" class="rounded border-stone-300 text-sm">
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                        <option value="digital_wallet">Digital Wallet</option>
                    </select>
                    <input v-model="accountForm.opening_balance" type="number" step="0.01" placeholder="Opening balance" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="accountForm.processing" class="col-span-3 rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">Add</button>
                </form>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Name</th><th>Type</th><th>Session</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in accounts" :key="a.id" class="border-b border-stone-100">
                            <td class="py-2 font-medium text-stone-900">{{ a.name }}</td>
                            <td class="text-stone-500">{{ a.account_type }}</td>
                            <td>
                                <span v-if="a.open_session" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">
                                    Open — float {{ money(a.open_session.opening_float) }}
                                </span>
                                <span v-else class="rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600">Closed</span>
                            </td>
                            <td class="text-right">
                                <div v-if="a.open_session" class="flex items-center justify-end gap-2">
                                    <input v-model="closingAmounts[a.open_session.id]" type="number" step="0.01" placeholder="Counted amount" class="w-32 rounded border-stone-300 text-xs">
                                    <button type="button" @click="closeSession(a.open_session.id, closingAmounts[a.open_session.id])" class="text-xs text-indigo-700 underline hover:text-indigo-800">Close</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="accounts.length === 0"><td colspan="4" class="py-3 text-center text-stone-400">No accounts yet.</td></tr>
                    </tbody>
                </table>
            </section>

            <!-- Open session -->
            <section v-if="closedCashAccounts.length > 0" class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <button type="button" @click="toggle('open')" class="text-base font-medium text-stone-900">
                    Open Session {{ activePanel === 'open' ? '▲' : '▼' }}
                </button>

                <form v-if="activePanel === 'open'" @submit.prevent="submitOpen" class="mt-3 grid grid-cols-3 gap-2">
                    <select v-model="openForm.financial_account_id" class="rounded border-stone-300 text-sm">
                        <option v-for="a in closedCashAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                    <input v-model="openForm.opening_float" type="number" step="0.01" placeholder="Opening float" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="openForm.processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">Open</button>
                </form>
            </section>

            <!-- Recent sessions -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">Recent Sessions</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Account</th><th>Opened</th><th>Float</th><th>Counted</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in recentSessions" :key="s.id" class="border-b border-stone-100">
                            <td class="py-2">{{ s.account }}</td>
                            <td class="text-stone-500">{{ s.opened_at }}</td>
                            <td class="tabular-nums">{{ money(s.opening_float) }}</td>
                            <td class="tabular-nums">{{ s.counted_closing !== null ? money(s.counted_closing) : '—' }}</td>
                            <td>
                                <span class="rounded-full px-2 py-0.5 text-xs" :class="s.status === 'open' ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-stone-600'">
                                    {{ s.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="recentSessions.length === 0"><td colspan="5" class="py-3 text-center text-stone-400">No sessions yet.</td></tr>
                    </tbody>
                </table>
            </section>
        </main>
    </AppLayout>
</template>
