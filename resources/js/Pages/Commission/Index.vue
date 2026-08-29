<script setup>
import { ref, watch } from 'vue';
import { usePage, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    employees: { type: Array, default: () => [] },
    rules: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    entries: { type: Array, default: () => [] },
    openPeriods: { type: Array, default: () => [] },
    finalizedEntries: { type: Array, default: () => [] },
});

const page = usePage();
const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);
}

// --- Commission rules -------------------------------------------------

const ruleForm = useForm({
    employee_id: props.employees[0]?.id ?? null,
    rate: '10',
    effective_from: new Date().toISOString().slice(0, 10),
});

watch(() => props.employees, (list) => {
    if (ruleForm.employee_id === null && list.length > 0) ruleForm.employee_id = list[0].id;
});

function submitRule() {
    ruleForm.post('/commission/rules', { preserveScroll: true, onSuccess: () => ruleForm.reset('rate') });
}

// --- Financial periods -------------------------------------------------

const periodForm = useForm({
    period_start: new Date().toISOString().slice(0, 10),
    period_end: new Date().toISOString().slice(0, 10),
});

function submitPeriod() {
    periodForm.post('/commission/periods', { preserveScroll: true });
}

// --- Calculate -----------------------------------------------------------

const calculateForm = useForm({ financial_period_id: props.periods[0]?.id ?? null });

watch(() => props.periods, (list) => {
    if (calculateForm.financial_period_id === null && list.length > 0) calculateForm.financial_period_id = list[0].id;
});

function submitCalculate() {
    calculateForm.post('/commission/calculate', { preserveScroll: true });
}

// --- Entry lifecycle actions ---------------------------------------------

function approve(entryId) {
    router.post(`/commission/entries/${entryId}/approve`, {}, { preserveScroll: true });
}
function finalize(entryId) {
    router.post(`/commission/entries/${entryId}/finalize`, {}, { preserveScroll: true });
}
function pay(entryId) {
    router.post(`/commission/entries/${entryId}/pay`, {}, { preserveScroll: true });
}

// --- Correction ------------------------------------------------------------

const correctionForm = useForm({
    original_commission_entry_id: props.finalizedEntries[0]?.id ?? null,
    financial_period_id: props.openPeriods[0]?.id ?? null,
    amount: '',
    reason: 'sale_return',
});

watch(() => props.finalizedEntries, (list) => {
    if (correctionForm.original_commission_entry_id === null && list.length > 0) correctionForm.original_commission_entry_id = list[0].id;
});
watch(() => props.openPeriods, (list) => {
    if (correctionForm.financial_period_id === null && list.length > 0) correctionForm.financial_period_id = list[0].id;
});

function submitCorrection() {
    correctionForm.post('/commission/corrections', { preserveScroll: true, onSuccess: () => correctionForm.reset('amount') });
}
</script>

<template>
    <div class="min-h-screen bg-stone-100">
        <header class="flex items-center justify-between border-b border-stone-200 bg-white px-6 py-4">
            <h1 class="text-lg font-semibold text-stone-900">Commission</h1>
            <span class="text-sm text-stone-500">{{ page.props.auth.user?.name }}</span>
        </header>

        <div v-if="page.props.flash?.success" class="mx-6 mt-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ page.props.flash.success }}
        </div>
        <div v-if="Object.keys(page.props.errors ?? {}).length" class="mx-6 mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p v-for="(msg, key) in page.props.errors" :key="key">{{ msg }}</p>
        </div>

        <main class="mx-auto max-w-4xl space-y-6 p-6">
            <!-- Rules -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <button type="button" @click="toggle('rule')" class="text-base font-medium text-stone-900">
                    Commission Rules {{ activePanel === 'rule' ? '▲' : '▼' }}
                </button>

                <form v-if="activePanel === 'rule'" @submit.prevent="submitRule" class="mt-3 grid grid-cols-4 gap-2">
                    <select v-model="ruleForm.employee_id" class="rounded border-stone-300 text-sm">
                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                    <input v-model="ruleForm.rate" type="number" step="0.01" placeholder="Rate %" class="rounded border-stone-300 text-sm">
                    <input v-model="ruleForm.effective_from" type="date" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="ruleForm.processing" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Add Rule</button>
                </form>

                <table class="mt-3 w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Employee</th><th>Rate</th><th>Effective From</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rules" :key="r.id" class="border-b border-stone-100">
                            <td class="py-2">{{ r.employee }}</td>
                            <td class="tabular-nums">{{ r.rate }}%</td>
                            <td>{{ r.effective_from }}</td>
                            <td>{{ r.status }}</td>
                        </tr>
                        <tr v-if="rules.length === 0"><td colspan="4" class="py-3 text-center text-stone-400">No rules yet.</td></tr>
                    </tbody>
                </table>
            </section>

            <!-- Financial periods + calculate -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <button type="button" @click="toggle('period')" class="text-base font-medium text-stone-900">
                    Financial Periods {{ activePanel === 'period' ? '▲' : '▼' }}
                </button>

                <form v-if="activePanel === 'period'" @submit.prevent="submitPeriod" class="mt-3 grid grid-cols-3 gap-2">
                    <input v-model="periodForm.period_start" type="date" class="rounded border-stone-300 text-sm">
                    <input v-model="periodForm.period_end" type="date" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="periodForm.processing" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Create Period</button>
                </form>

                <div v-if="periods.length > 0" class="mt-4 border-t border-stone-200 pt-3">
                    <p class="mb-2 text-sm text-stone-700">Calculate commission for a period</p>
                    <form @submit.prevent="submitCalculate" class="flex gap-2">
                        <select v-model="calculateForm.financial_period_id" class="flex-1 rounded border-stone-300 text-sm">
                            <option v-for="p in periods" :key="p.id" :value="p.id">
                                {{ p.period_start }} – {{ p.period_end }} ({{ p.status }})
                            </option>
                        </select>
                        <button type="submit" :disabled="calculateForm.processing" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Calculate</button>
                    </form>
                </div>
            </section>

            <!-- Entries -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">Commission Entries</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Employee</th><th>Period</th><th>Gross Profit</th><th>Rate</th><th>Commission</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in entries" :key="e.id" class="border-b border-stone-100">
                            <td class="py-2">{{ e.employee }}</td>
                            <td class="text-stone-500">{{ e.period }}</td>
                            <td class="tabular-nums">{{ money(e.eligible_gross_profit) }}</td>
                            <td class="tabular-nums">{{ e.rate_applied }}%</td>
                            <td class="tabular-nums font-medium">{{ money(e.commission_amount) }}</td>
                            <td>
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-700">{{ e.status }}</span>
                            </td>
                            <td class="text-right">
                                <button v-if="e.status === 'calculated'" type="button" @click="approve(e.id)" class="text-xs text-stone-600 underline">Approve</button>
                                <button v-else-if="e.status === 'approved'" type="button" @click="finalize(e.id)" class="text-xs text-stone-600 underline">Finalize</button>
                                <button v-else-if="e.status === 'finalized'" type="button" @click="pay(e.id)" class="text-xs text-stone-600 underline">Pay</button>
                            </td>
                        </tr>
                        <tr v-if="entries.length === 0"><td colspan="7" class="py-3 text-center text-stone-400">No commission entries yet.</td></tr>
                    </tbody>
                </table>
            </section>

            <!-- Correction -->
            <section v-if="finalizedEntries.length > 0" class="rounded-lg bg-white p-4 shadow-sm">
                <button type="button" @click="toggle('correction')" class="text-base font-medium text-stone-900">
                    Record Correction {{ activePanel === 'correction' ? '▲' : '▼' }}
                </button>
                <p v-if="activePanel === 'correction'" class="mt-1 text-xs text-stone-500">
                    Always lands in the currently open period — never edits the finalized entry it corrects.
                </p>

                <form v-if="activePanel === 'correction'" @submit.prevent="submitCorrection" class="mt-3 grid grid-cols-2 gap-2">
                    <select v-model="correctionForm.original_commission_entry_id" class="rounded border-stone-300 text-sm">
                        <option v-for="e in finalizedEntries" :key="e.id" :value="e.id">{{ e.employee }} — {{ e.period }}</option>
                    </select>
                    <select v-model="correctionForm.financial_period_id" class="rounded border-stone-300 text-sm">
                        <option v-for="p in openPeriods" :key="p.id" :value="p.id">Open period: {{ p.period_start }} – {{ p.period_end }}</option>
                    </select>
                    <input v-model="correctionForm.amount" type="number" step="0.01" placeholder="Amount (e.g. -50)" class="rounded border-stone-300 text-sm">
                    <select v-model="correctionForm.reason" class="rounded border-stone-300 text-sm">
                        <option value="sale_return">Sale return</option>
                        <option value="sale_cancellation">Sale cancellation</option>
                        <option value="manual_adjustment">Manual adjustment</option>
                    </select>
                    <button type="submit" :disabled="correctionForm.processing || openPeriods.length === 0" class="col-span-2 rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700 disabled:opacity-50">
                        Save Correction
                    </button>
                    <p v-if="openPeriods.length === 0" class="col-span-2 text-xs text-red-600">No open financial period exists to land this correction in.</p>
                </form>
            </section>
        </main>
    </div>
</template>
