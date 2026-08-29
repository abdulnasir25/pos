<script setup>
import { computed, ref } from 'vue';
import { usePage, useForm } from '@inertiajs/vue3';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    entries: { type: Array, default: () => [] },
});

const page = usePage();
const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);
}

// --- Add account -----------------------------------------------------------

const accountForm = useForm({ code: '', name: '', type: 'asset', parent_id: '' });

function submitAccount() {
    accountForm.post('/accounting/accounts', { preserveScroll: true, onSuccess: () => accountForm.reset('code', 'name') });
}

// --- Journal entry -------------------------------------------------------

const entryForm = useForm({
    entry_date: new Date().toISOString().slice(0, 10),
    description: '',
    lines: [
        { account_id: props.accounts[0]?.id ?? null, debit: '', credit: '' },
        { account_id: props.accounts[0]?.id ?? null, debit: '', credit: '' },
    ],
});

function addLine() {
    entryForm.lines.push({ account_id: props.accounts[0]?.id ?? null, debit: '', credit: '' });
}
function removeLine(index) {
    entryForm.lines.splice(index, 1);
}

const totalDebits = computed(() => entryForm.lines.reduce((sum, l) => sum + (parseFloat(l.debit) || 0), 0));
const totalCredits = computed(() => entryForm.lines.reduce((sum, l) => sum + (parseFloat(l.credit) || 0), 0));
const isBalanced = computed(() => totalDebits.value > 0 && totalDebits.value.toFixed(2) === totalCredits.value.toFixed(2));

function submitEntry() {
    entryForm.post('/accounting/entries', {
        preserveScroll: true,
        onSuccess: () => {
            entryForm.reset('description');
            entryForm.lines = [
                { account_id: props.accounts[0]?.id ?? null, debit: '', credit: '' },
                { account_id: props.accounts[0]?.id ?? null, debit: '', credit: '' },
            ];
        },
    });
}
</script>

<template>
    <div class="min-h-screen bg-stone-100">
        <header class="flex items-center justify-between border-b border-stone-200 bg-white px-6 py-4">
            <h1 class="text-lg font-semibold text-stone-900">Accounting</h1>
            <span class="text-sm text-stone-500">{{ page.props.auth.user?.name }}</span>
        </header>

        <div v-if="page.props.flash?.success" class="mx-6 mt-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ page.props.flash.success }}
        </div>
        <div v-if="Object.keys(page.props.errors ?? {}).length" class="mx-6 mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p v-for="(msg, key) in page.props.errors" :key="key">{{ msg }}</p>
        </div>

        <main class="mx-auto max-w-4xl space-y-6 p-6">
            <!-- Chart of accounts -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">Chart of Accounts</h2>
                    <button type="button" @click="toggle('account')" class="text-sm text-stone-600 underline">+ Add Account</button>
                </div>

                <form v-if="activePanel === 'account'" @submit.prevent="submitAccount" class="mb-4 grid grid-cols-4 gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="accountForm.code" type="text" placeholder="Code (e.g. 3001)" class="rounded border-stone-300 text-sm">
                    <input v-model="accountForm.name" type="text" placeholder="Name" class="rounded border-stone-300 text-sm">
                    <select v-model="accountForm.type" class="rounded border-stone-300 text-sm">
                        <option value="asset">Asset</option>
                        <option value="liability">Liability</option>
                        <option value="equity">Equity</option>
                        <option value="contra_equity">Contra-Equity</option>
                        <option value="revenue">Revenue</option>
                        <option value="expense">Expense</option>
                    </select>
                    <select v-model="accountForm.parent_id" class="rounded border-stone-300 text-sm">
                        <option value="">No parent</option>
                        <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                    </select>
                    <button type="submit" :disabled="accountForm.processing" class="col-span-4 rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Add</button>
                </form>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Code</th><th>Name</th><th>Type</th><th class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in accounts" :key="a.id" class="border-b border-stone-100">
                            <td class="py-2 font-mono text-xs text-stone-500">{{ a.code }}</td>
                            <td class="text-stone-900">{{ a.name }}</td>
                            <td class="text-stone-500">{{ a.type }}</td>
                            <td class="text-right tabular-nums" :class="parseFloat(a.balance) < 0 ? 'text-red-600' : ''">{{ money(a.balance) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Journal entry -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <button type="button" @click="toggle('entry')" class="text-base font-medium text-stone-900">
                    Post Journal Entry {{ activePanel === 'entry' ? '▲' : '▼' }}
                </button>

                <form v-if="activePanel === 'entry'" @submit.prevent="submitEntry" class="mt-3">
                    <div class="mb-3 flex gap-2">
                        <input v-model="entryForm.entry_date" type="date" class="rounded border-stone-300 text-sm">
                        <input v-model="entryForm.description" type="text" placeholder="Description (optional)" class="flex-1 rounded border-stone-300 text-sm">
                    </div>

                    <div v-for="(line, index) in entryForm.lines" :key="index" class="mb-2 grid grid-cols-4 gap-2">
                        <select v-model="line.account_id" class="col-span-2 rounded border-stone-300 text-sm">
                            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                        </select>
                        <input v-model="line.debit" type="number" step="0.01" placeholder="Debit" class="rounded border-stone-300 text-sm">
                        <div class="flex gap-1">
                            <input v-model="line.credit" type="number" step="0.01" placeholder="Credit" class="w-full rounded border-stone-300 text-sm">
                            <button v-if="entryForm.lines.length > 2" type="button" @click="removeLine(index)" class="text-xs text-red-600">✕</button>
                        </div>
                    </div>

                    <button type="button" @click="addLine" class="text-xs text-stone-600 underline">+ Add line</button>

                    <div class="mt-3 flex items-center justify-between border-t border-stone-200 pt-3 text-sm">
                        <span>Debits: <span class="font-medium tabular-nums">{{ money(totalDebits) }}</span> · Credits: <span class="font-medium tabular-nums">{{ money(totalCredits) }}</span></span>
                        <span :class="isBalanced ? 'text-emerald-700' : 'text-red-600'">{{ isBalanced ? 'Balanced' : 'Not balanced' }}</span>
                    </div>

                    <button type="submit" :disabled="!isBalanced || entryForm.processing" class="mt-3 w-full rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 disabled:opacity-50">
                        Post Entry
                    </button>
                </form>
            </section>

            <!-- Recent entries -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">Recent Journal Entries</h2>
                <div v-for="e in entries" :key="e.id" class="mb-3 rounded-md border border-stone-200 p-2 text-sm">
                    <div class="flex justify-between">
                        <span class="font-medium">{{ e.entry_date }}</span>
                        <span class="text-stone-500">{{ e.description ?? '—' }}</span>
                    </div>
                    <div v-for="(line, i) in e.lines" :key="i" class="mt-1 flex justify-between text-xs text-stone-600">
                        <span>{{ line.account }}</span>
                        <span class="tabular-nums">
                            {{ parseFloat(line.debit) > 0 ? `Dr ${money(line.debit)}` : `Cr ${money(line.credit)}` }}
                        </span>
                    </div>
                </div>
                <p v-if="entries.length === 0" class="text-sm text-stone-400">No journal entries yet.</p>
            </section>
        </main>
    </div>
</template>
