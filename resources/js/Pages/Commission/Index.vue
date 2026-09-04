<script setup>
import { ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PlusIcon from '../../Components/icons/PlusIcon.vue';
import ChevronDownIcon from '../../Components/icons/ChevronDownIcon.vue';
import TrashIcon from '../../Components/icons/TrashIcon.vue';
import CheckIcon from '../../Components/icons/CheckIcon.vue';
import { useI18n } from '../../i18n';

const props = defineProps({
    employees: { type: Array, default: () => [] },
    rules: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    entries: { type: Array, default: () => [] },
    openPeriods: { type: Array, default: () => [] },
    finalizedEntries: { type: Array, default: () => [] },
});

const { t } = useI18n();
const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);
}

const periodStatusLabels = {
    open: () => t('financial_periods.status_open'),
    calculating: () => t('financial_periods.status_calculating'),
    under_review: () => t('financial_periods.status_under_review'),
    closed: () => t('financial_periods.status_closed'),
};
function periodStatusLabel(status) {
    return periodStatusLabels[status]?.() ?? status;
}

const entryStatusLabels = {
    calculated: () => t('commission.status_calculated'),
    approved: () => t('commission.status_approved'),
    finalized: () => t('commission.status_finalized'),
    paid: () => t('commission.status_paid'),
};
function entryStatusLabel(status) {
    return entryStatusLabels[status]?.() ?? status;
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

function toggleRuleStatus(ruleId) {
    router.post(`/commission/rules/${ruleId}/toggle-status`, {}, { preserveScroll: true });
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
    <AppLayout :title="t('commission.title')">
        <main class="mx-auto max-w-4xl space-y-6 p-6">
            <!-- Rules -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <button type="button" @click="toggle('rule')" class="flex items-center gap-2 text-base font-medium text-stone-900">
                    {{ t('commission.rules_title') }}
                    <ChevronDownIcon class="size-4 transition-transform" :class="{ 'rotate-180': activePanel === 'rule' }" />
                </button>

                <form v-if="activePanel === 'rule'" @submit.prevent="submitRule" class="mt-3 grid grid-cols-1 sm:grid-cols-4 gap-2">
                    <select v-model="ruleForm.employee_id" class="rounded border-stone-300 text-sm">
                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                    <input v-model="ruleForm.rate" type="number" step="0.01" :placeholder="t('commission.rate_placeholder')" class="rounded border-stone-300 text-sm">
                    <input v-model="ruleForm.effective_from" type="date" class="rounded border-stone-300 text-sm">
                    <button
                        type="submit"
                        :disabled="ruleForm.processing"
                        :aria-label="t('common.add')"
                        class="flex items-center justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </form>

                <div class="overflow-x-auto">
                <table class="mt-3 w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">{{ t('commission.employee') }}</th><th>{{ t('commission.rate') }}</th><th>{{ t('commission.effective_from') }}</th><th>{{ t('commission.status') }}</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rules" :key="r.id" class="border-b border-stone-100">
                            <td class="py-2">{{ r.employee }}</td>
                            <td class="tabular-nums">{{ r.rate }}%</td>
                            <td>{{ r.effective_from }}</td>
                            <td>{{ r.status === 'active' ? t('common.active') : t('common.inactive') }}</td>
                            <td class="text-right">
                                <button
                                    type="button"
                                    @click="toggleRuleStatus(r.id)"
                                    :aria-label="r.status === 'active' ? t('common.deactivate') : t('common.activate')"
                                    class="inline-flex size-6 items-center justify-center rounded-full text-stone-500 hover:bg-stone-100"
                                    :class="r.status === 'active' ? 'hover:text-red-700' : 'hover:text-emerald-700'"
                                >
                                    <TrashIcon v-if="r.status === 'active'" class="size-3.5" />
                                    <CheckIcon v-else class="size-3.5" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="rules.length === 0"><td colspan="5" class="py-3 text-center text-stone-400">{{ t('commission.no_rules') }}</td></tr>
                    </tbody>
                </table>
                </div>
            </section>

            <!-- Financial periods + calculate -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <button type="button" @click="toggle('period')" class="flex items-center gap-2 text-base font-medium text-stone-900">
                    {{ t('commission.periods_title') }}
                    <ChevronDownIcon class="size-4 transition-transform" :class="{ 'rotate-180': activePanel === 'period' }" />
                </button>

                <form v-if="activePanel === 'period'" @submit.prevent="submitPeriod" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <input v-model="periodForm.period_start" type="date" class="rounded border-stone-300 text-sm">
                    <input v-model="periodForm.period_end" type="date" class="rounded border-stone-300 text-sm">
                    <button
                        type="submit"
                        :disabled="periodForm.processing"
                        :aria-label="t('common.add')"
                        class="flex items-center justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </form>

                <div v-if="periods.length > 0" class="mt-4 border-t border-stone-200 pt-3">
                    <p class="mb-2 text-sm text-stone-700">{{ t('commission.calculate_hint') }}</p>
                    <form @submit.prevent="submitCalculate" class="flex gap-2">
                        <select v-model="calculateForm.financial_period_id" class="flex-1 rounded border-stone-300 text-sm">
                            <option v-for="p in periods" :key="p.id" :value="p.id">
                                {{ p.period_start }} – {{ p.period_end }} ({{ periodStatusLabel(p.status) }})
                            </option>
                        </select>
                        <button type="submit" :disabled="calculateForm.processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">{{ t('commission.calculate') }}</button>
                    </form>
                </div>
            </section>

            <!-- Entries -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">{{ t('commission.entries') }}</h2>
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">{{ t('commission.employee') }}</th><th>{{ t('commission.period') }}</th><th>{{ t('commission.gross_profit') }}</th><th>{{ t('commission.rate') }}</th><th>{{ t('commission.commission') }}</th><th>{{ t('commission.status') }}</th><th></th>
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
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-700">{{ entryStatusLabel(e.status) }}</span>
                            </td>
                            <td class="text-right">
                                <button v-if="e.status === 'calculated'" type="button" @click="approve(e.id)" class="text-xs text-indigo-700 underline hover:text-indigo-800">{{ t('commission.approve') }}</button>
                                <button v-else-if="e.status === 'approved'" type="button" @click="finalize(e.id)" class="text-xs text-indigo-700 underline hover:text-indigo-800">{{ t('commission.finalize') }}</button>
                                <button v-else-if="e.status === 'finalized'" type="button" @click="pay(e.id)" class="text-xs text-indigo-700 underline hover:text-indigo-800">{{ t('commission.pay') }}</button>
                            </td>
                        </tr>
                        <tr v-if="entries.length === 0"><td colspan="7" class="py-3 text-center text-stone-400">{{ t('commission.none_yet') }}</td></tr>
                    </tbody>
                </table>
                </div>
            </section>

            <!-- Correction -->
            <section v-if="finalizedEntries.length > 0" class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <button type="button" @click="toggle('correction')" class="flex items-center gap-2 text-base font-medium text-stone-900">
                    {{ t('commission.correction') }}
                    <ChevronDownIcon class="size-4 transition-transform" :class="{ 'rotate-180': activePanel === 'correction' }" />
                </button>
                <p v-if="activePanel === 'correction'" class="mt-1 text-xs text-stone-500">
                    {{ t('commission.correction_note') }}
                </p>

                <form v-if="activePanel === 'correction'" @submit.prevent="submitCorrection" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <select v-model="correctionForm.original_commission_entry_id" class="rounded border-stone-300 text-sm">
                        <option v-for="e in finalizedEntries" :key="e.id" :value="e.id">{{ e.employee }} — {{ e.period }}</option>
                    </select>
                    <select v-model="correctionForm.financial_period_id" class="rounded border-stone-300 text-sm">
                        <option v-for="p in openPeriods" :key="p.id" :value="p.id">{{ t('commission.open_period_option', { range: `${p.period_start} – ${p.period_end}` }) }}</option>
                    </select>
                    <input v-model="correctionForm.amount" type="number" step="0.01" :placeholder="t('commission.amount_placeholder')" class="rounded border-stone-300 text-sm">
                    <select v-model="correctionForm.reason" class="rounded border-stone-300 text-sm">
                        <option value="sale_return">{{ t('commission.reason_return') }}</option>
                        <option value="sale_cancellation">{{ t('commission.reason_cancel') }}</option>
                        <option value="manual_adjustment">{{ t('commission.reason_manual') }}</option>
                    </select>
                    <button type="submit" :disabled="correctionForm.processing || openPeriods.length === 0" class="col-span-2 rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700 disabled:opacity-50">
                        {{ t('commission.save_correction') }}
                    </button>
                    <p v-if="openPeriods.length === 0" class="col-span-2 text-xs text-red-600">{{ t('commission.no_open_period') }}</p>
                </form>
            </section>
        </main>
    </AppLayout>
</template>
