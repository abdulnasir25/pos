<script setup>
import { ref, computed } from 'vue';
import { usePage, useForm } from '@inertiajs/vue3';

const props = defineProps({
    partners: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
});

const page = usePage();
const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

// --- Add partner ---------------------------------------------------------

const partnerForm = useForm({ name: '', phone: '', joined_at: new Date().toISOString().slice(0, 10) });

function submitPartner() {
    partnerForm.post('/partners', { preserveScroll: true, onSuccess: () => partnerForm.reset('name', 'phone') });
}

// --- Rebalance ownership --------------------------------------------------

const activePartners = computed(() => props.partners.filter((p) => p.status === 'active'));

const rebalanceForm = useForm({
    effective_from: new Date().toISOString().slice(0, 10),
    percentages: Object.fromEntries(activePartners.value.map((p) => [p.id, p.ownership_percentage ?? '0.00'])),
});

const rebalanceSum = computed(() =>
    Object.values(rebalanceForm.percentages).reduce((sum, v) => sum + (parseFloat(v) || 0), 0)
);

function submitRebalance() {
    rebalanceForm.post('/partners/rebalance', { preserveScroll: true });
}

// --- Capital ---------------------------------------------------------------

const capitalForm = useForm({
    partner_id: props.partners[0]?.id ?? null,
    type: 'contribution',
    amount: '',
    entry_date: new Date().toISOString().slice(0, 10),
});

function submitCapital() {
    capitalForm.post('/partners/capital', { preserveScroll: true, onSuccess: () => capitalForm.reset('amount') });
}

// --- Loan --------------------------------------------------------------------

const loanForm = useForm({
    partner_id: props.partners[0]?.id ?? null,
    principal_amount: '',
    issued_at: new Date().toISOString().slice(0, 10),
});

function submitLoan() {
    loanForm.post('/partners/loans', { preserveScroll: true, onSuccess: () => loanForm.reset('principal_amount') });
}

// --- Repayment -----------------------------------------------------------------

const outstandingLoans = computed(() =>
    props.partners.flatMap((p) => p.loans.map((l) => ({ ...l, partner_name: p.name })))
);

const repaymentForm = useForm({
    loan_id: outstandingLoans.value[0]?.id ?? null,
    amount: '',
    repaid_at: new Date().toISOString().slice(0, 10),
});

function submitRepayment() {
    repaymentForm.post('/partners/repayments', { preserveScroll: true, onSuccess: () => repaymentForm.reset('amount') });
}

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);
}
</script>

<template>
    <div class="min-h-screen bg-stone-100">
        <header class="flex items-center justify-between border-b border-stone-200 bg-white px-6 py-4">
            <h1 class="text-lg font-semibold text-stone-900">Partners</h1>
            <span class="text-sm text-stone-500">{{ page.props.auth.user?.name }}</span>
        </header>

        <div v-if="page.props.flash?.success" class="mx-6 mt-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ page.props.flash.success }}
        </div>
        <div v-if="Object.keys(page.props.errors ?? {}).length" class="mx-6 mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p v-for="(msg, key) in page.props.errors" :key="key">{{ msg }}</p>
        </div>

        <main class="mx-auto max-w-4xl space-y-6 p-6">
            <!-- Partners table -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">All Partners</h2>
                    <button type="button" @click="toggle('add')" class="text-sm text-stone-600 underline">+ Add Partner</button>
                </div>

                <form v-if="activePanel === 'add'" @submit.prevent="submitPartner" class="mb-4 grid grid-cols-3 gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="partnerForm.name" type="text" placeholder="Name" class="rounded border-stone-300 text-sm">
                    <input v-model="partnerForm.phone" type="text" placeholder="Phone (optional)" class="rounded border-stone-300 text-sm">
                    <input v-model="partnerForm.joined_at" type="date" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="partnerForm.processing" class="col-span-3 rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Add</button>
                </form>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Name</th>
                            <th>Ownership</th>
                            <th>Capital</th>
                            <th>Loan Owed</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in partners" :key="p.id" class="border-b border-stone-100">
                            <td class="py-2 font-medium text-stone-900">{{ p.name }}</td>
                            <td class="tabular-nums">{{ p.ownership_percentage ?? '—' }}%</td>
                            <td class="tabular-nums">{{ money(p.capital_balance) }}</td>
                            <td class="tabular-nums">{{ money(p.loan_balance) }}</td>
                            <td>
                                <span class="rounded-full px-2 py-0.5 text-xs" :class="p.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-200 text-stone-600'">
                                    {{ p.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Rebalance ownership -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <button type="button" @click="toggle('rebalance')" class="text-base font-medium text-stone-900">
                    Rebalance Ownership {{ activePanel === 'rebalance' ? '▲' : '▼' }}
                </button>

                <div v-if="activePanel === 'rebalance'" class="mt-3">
                    <p class="mb-2 text-xs text-stone-500">Every active partner's new percentage must be given at once — they must sum to exactly 100.</p>
                    <div class="mb-2 grid grid-cols-2 gap-2">
                        <div v-for="p in activePartners" :key="p.id" class="flex items-center gap-2">
                            <label class="w-32 text-sm text-stone-700">{{ p.name }}</label>
                            <input v-model="rebalanceForm.percentages[p.id]" type="number" step="0.01" class="w-24 rounded border-stone-300 text-sm">
                            <span class="text-xs text-stone-400">%</span>
                        </div>
                    </div>
                    <div class="mb-3 flex items-center gap-3">
                        <label class="text-sm text-stone-700">Effective from</label>
                        <input v-model="rebalanceForm.effective_from" type="date" class="rounded border-stone-300 text-sm">
                        <span class="text-sm" :class="rebalanceSum === 100 ? 'text-emerald-700' : 'text-red-600'">
                            Total: {{ rebalanceSum.toFixed(2) }}%
                        </span>
                    </div>
                    <button type="button" :disabled="rebalanceSum !== 100 || rebalanceForm.processing" @click="submitRebalance" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700 disabled:opacity-50">
                        Save Rebalance
                    </button>
                </div>
            </section>

            <!-- Capital -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <button type="button" @click="toggle('capital')" class="text-base font-medium text-stone-900">
                    Record Capital {{ activePanel === 'capital' ? '▲' : '▼' }}
                </button>

                <form v-if="activePanel === 'capital'" @submit.prevent="submitCapital" class="mt-3 grid grid-cols-4 gap-2">
                    <select v-model="capitalForm.partner_id" class="rounded border-stone-300 text-sm">
                        <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <select v-model="capitalForm.type" class="rounded border-stone-300 text-sm">
                        <option value="contribution">Contribution</option>
                        <option value="withdrawal">Withdrawal</option>
                    </select>
                    <input v-model="capitalForm.amount" type="number" step="0.01" placeholder="Amount" class="rounded border-stone-300 text-sm">
                    <input v-model="capitalForm.entry_date" type="date" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="capitalForm.processing" class="col-span-4 rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Save</button>
                </form>
            </section>

            <!-- Loans -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <button type="button" @click="toggle('loan')" class="text-base font-medium text-stone-900">
                    Issue Loan {{ activePanel === 'loan' ? '▲' : '▼' }}
                </button>

                <form v-if="activePanel === 'loan'" @submit.prevent="submitLoan" class="mt-3 grid grid-cols-3 gap-2">
                    <select v-model="loanForm.partner_id" class="rounded border-stone-300 text-sm">
                        <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <input v-model="loanForm.principal_amount" type="number" step="0.01" placeholder="Principal amount" class="rounded border-stone-300 text-sm">
                    <input v-model="loanForm.issued_at" type="date" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="loanForm.processing" class="col-span-3 rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Issue Loan</button>
                </form>
            </section>

            <!-- Repayments -->
            <section v-if="outstandingLoans.length > 0" class="rounded-lg bg-white p-4 shadow-sm">
                <button type="button" @click="toggle('repayment')" class="text-base font-medium text-stone-900">
                    Record Loan Repayment {{ activePanel === 'repayment' ? '▲' : '▼' }}
                </button>

                <form v-if="activePanel === 'repayment'" @submit.prevent="submitRepayment" class="mt-3 grid grid-cols-3 gap-2">
                    <select v-model="repaymentForm.loan_id" class="rounded border-stone-300 text-sm">
                        <option v-for="l in outstandingLoans" :key="l.id" :value="l.id">
                            {{ l.partner_name }} — owes {{ money(l.outstanding) }}
                        </option>
                    </select>
                    <input v-model="repaymentForm.amount" type="number" step="0.01" placeholder="Amount" class="rounded border-stone-300 text-sm">
                    <input v-model="repaymentForm.repaid_at" type="date" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="repaymentForm.processing" class="col-span-3 rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Record Repayment</button>
                </form>
            </section>
        </main>
    </div>
</template>
