<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PlusIcon from '../../Components/icons/PlusIcon.vue';
import PencilIcon from '../../Components/icons/PencilIcon.vue';
import TrashIcon from '../../Components/icons/TrashIcon.vue';
import ChevronDownIcon from '../../Components/icons/ChevronDownIcon.vue';
import { useI18n } from '../../i18n';

const props = defineProps({
    partners: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
});

const { t } = useI18n();
const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

// --- Add partner ---------------------------------------------------------

const partnerForm = useForm({ name: '', phone: '', joined_at: new Date().toISOString().slice(0, 10) });

function submitPartner() {
    partnerForm.post('/partners', { preserveScroll: true, onSuccess: () => partnerForm.reset('name', 'phone') });
}

// --- Edit partner ---------------------------------------------------------

const editForms = ref({});

function editForm(partnerId) {
    if (!editForms.value[partnerId]) {
        const partner = props.partners.find((p) => p.id === partnerId);
        editForms.value[partnerId] = useForm({ name: partner.name, phone: partner.phone });
    }
    return editForms.value[partnerId];
}

function submitEdit(partnerId) {
    editForm(partnerId).post(`/partners/${partnerId}/profile`, {
        preserveScroll: true,
        onSuccess: () => toggle(null),
    });
}

// --- Exit partner ---------------------------------------------------------

const exitForms = ref({});

function exitForm(partnerId) {
    if (!exitForms.value[partnerId]) {
        exitForms.value[partnerId] = useForm({ exited_at: new Date().toISOString().slice(0, 10) });
    }
    return exitForms.value[partnerId];
}

function submitExit(partnerId) {
    exitForm(partnerId).post(`/partners/${partnerId}/exit`, {
        preserveScroll: true,
        onSuccess: () => toggle(null),
    });
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
    <AppLayout :title="t('partners.title')">
        <main class="mx-auto max-w-4xl space-y-6 p-6">
            <!-- Partners table -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">{{ t('partners.list_title') }}</h2>
                    <button
                        type="button"
                        @click="toggle('add')"
                        :aria-label="t('common.add')"
                        class="flex size-8 items-center justify-center rounded-full bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </div>

                <form v-if="activePanel === 'add'" @submit.prevent="submitPartner" class="mb-4 grid grid-cols-1 sm:grid-cols-4 gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="partnerForm.name" type="text" :placeholder="t('common.name')" class="rounded border-stone-300 text-sm">
                    <input v-model="partnerForm.phone" type="text" :placeholder="t('customers.phone_placeholder')" class="rounded border-stone-300 text-sm">
                    <input v-model="partnerForm.joined_at" type="date" class="rounded border-stone-300 text-sm">
                    <button
                        type="submit"
                        :disabled="partnerForm.processing"
                        :aria-label="t('common.add')"
                        class="flex items-center justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </form>

                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">{{ t('common.name') }}</th>
                            <th>{{ t('partners.ownership') }}</th>
                            <th>{{ t('partners.capital') }}</th>
                            <th>{{ t('partners.loan_owed') }}</th>
                            <th>{{ t('common.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="p in partners" :key="p.id">
                            <tr class="border-b border-stone-100">
                                <td class="py-2 font-medium text-stone-900">{{ p.name }}</td>
                                <td class="tabular-nums">{{ p.ownership_percentage ?? '—' }}%</td>
                                <td class="tabular-nums">{{ money(p.capital_balance) }}</td>
                                <td class="tabular-nums">{{ money(p.loan_balance) }}</td>
                                <td>
                                    <span class="rounded-full px-2 py-0.5 text-xs" :class="p.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-200 text-stone-600'">
                                        {{ p.status === 'active' ? t('common.active') : t('partners.exited') }}
                                    </span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <button
                                        type="button"
                                        @click="toggle(`edit-${p.id}`)"
                                        :aria-label="t('partners.edit_title')"
                                        class="mr-1 inline-flex size-7 items-center justify-center rounded-full text-indigo-600 hover:bg-indigo-50"
                                    >
                                        <PencilIcon class="size-3.5" />
                                    </button>
                                    <button
                                        v-if="p.status === 'active'"
                                        type="button"
                                        @click="toggle(`exit-${p.id}`)"
                                        :aria-label="t('partners.exit_action')"
                                        class="inline-flex size-7 items-center justify-center rounded-full text-red-600 hover:bg-red-50"
                                    >
                                        <TrashIcon class="size-3.5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="activePanel === `edit-${p.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="6" class="p-2">
                                    <div class="flex items-center gap-2">
                                        <input v-model="editForm(p.id).name" type="text" :placeholder="t('common.name')" class="rounded border-stone-300 text-sm">
                                        <input v-model="editForm(p.id).phone" type="text" :placeholder="t('customers.phone_placeholder')" class="rounded border-stone-300 text-sm">
                                        <button type="button" @click="submitEdit(p.id)" :disabled="editForm(p.id).processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs text-white hover:bg-indigo-700">{{ t('common.save') }}</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="activePanel === `exit-${p.id}`" class="border-b border-stone-100 bg-red-50/40">
                                <td colspan="6" class="p-2">
                                    <p class="mb-2 text-xs text-stone-500">{{ t('partners.exit_note') }}</p>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-stone-500">{{ t('partners.exited_on') }}</span>
                                        <input v-model="exitForm(p.id).exited_at" type="date" class="rounded border-stone-300 text-sm">
                                        <button type="button" @click="submitExit(p.id)" :disabled="exitForm(p.id).processing" class="rounded-md bg-red-600 px-3 py-1.5 text-xs text-white hover:bg-red-700">{{ t('partners.exit_action') }}</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                </div>
            </section>

            <!-- Rebalance ownership -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <button type="button" @click="toggle('rebalance')" class="flex items-center gap-2 text-base font-medium text-stone-900">
                    {{ t('partners.rebalance') }}
                    <ChevronDownIcon class="size-4 transition-transform" :class="{ 'rotate-180': activePanel === 'rebalance' }" />
                </button>

                <div v-if="activePanel === 'rebalance'" class="mt-3">
                    <p class="mb-2 text-xs text-stone-500">{{ t('partners.every_partner_note') }}</p>
                    <div class="mb-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div v-for="p in activePartners" :key="p.id" class="flex items-center gap-2">
                            <label class="w-32 text-sm text-stone-700">{{ p.name }}</label>
                            <input v-model="rebalanceForm.percentages[p.id]" type="number" step="0.01" class="w-24 rounded border-stone-300 text-sm">
                            <span class="text-xs text-stone-400">%</span>
                        </div>
                    </div>
                    <div class="mb-3 flex items-center gap-3">
                        <label class="text-sm text-stone-700">{{ t('partners.effective_from') }}</label>
                        <input v-model="rebalanceForm.effective_from" type="date" class="rounded border-stone-300 text-sm">
                        <span class="text-sm" :class="rebalanceSum === 100 ? 'text-emerald-700' : 'text-red-600'">
                            {{ t('partners.total') }}: {{ rebalanceSum.toFixed(2) }}%
                        </span>
                    </div>
                    <button type="button" :disabled="rebalanceSum !== 100 || rebalanceForm.processing" @click="submitRebalance" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700 disabled:opacity-50">
                        {{ t('partners.save_rebalance') }}
                    </button>
                </div>
            </section>

            <!-- Capital -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <button type="button" @click="toggle('capital')" class="flex items-center gap-2 text-base font-medium text-stone-900">
                    {{ t('partners.record_capital') }}
                    <ChevronDownIcon class="size-4 transition-transform" :class="{ 'rotate-180': activePanel === 'capital' }" />
                </button>

                <form v-if="activePanel === 'capital'" @submit.prevent="submitCapital" class="mt-3 grid grid-cols-1 sm:grid-cols-4 gap-2">
                    <select v-model="capitalForm.partner_id" class="rounded border-stone-300 text-sm">
                        <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <select v-model="capitalForm.type" class="rounded border-stone-300 text-sm">
                        <option value="contribution">{{ t('partners.contribution') }}</option>
                        <option value="withdrawal">{{ t('partners.withdrawal') }}</option>
                    </select>
                    <input v-model="capitalForm.amount" type="number" step="0.01" :placeholder="t('partners.amount_placeholder')" class="rounded border-stone-300 text-sm">
                    <input v-model="capitalForm.entry_date" type="date" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="capitalForm.processing" class="col-span-4 rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">{{ t('common.save') }}</button>
                </form>
            </section>

            <!-- Loans -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <button type="button" @click="toggle('loan')" class="flex items-center gap-2 text-base font-medium text-stone-900">
                    {{ t('partners.issue_loan') }}
                    <ChevronDownIcon class="size-4 transition-transform" :class="{ 'rotate-180': activePanel === 'loan' }" />
                </button>

                <form v-if="activePanel === 'loan'" @submit.prevent="submitLoan" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <select v-model="loanForm.partner_id" class="rounded border-stone-300 text-sm">
                        <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <input v-model="loanForm.principal_amount" type="number" step="0.01" :placeholder="t('partners.principal_placeholder')" class="rounded border-stone-300 text-sm">
                    <input v-model="loanForm.issued_at" type="date" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="loanForm.processing" class="col-span-3 rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">{{ t('partners.issue_loan_action') }}</button>
                </form>
            </section>

            <!-- Repayments -->
            <section v-if="outstandingLoans.length > 0" class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <button type="button" @click="toggle('repayment')" class="flex items-center gap-2 text-base font-medium text-stone-900">
                    {{ t('partners.record_repayment') }}
                    <ChevronDownIcon class="size-4 transition-transform" :class="{ 'rotate-180': activePanel === 'repayment' }" />
                </button>

                <form v-if="activePanel === 'repayment'" @submit.prevent="submitRepayment" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <select v-model="repaymentForm.loan_id" class="rounded border-stone-300 text-sm">
                        <option v-for="l in outstandingLoans" :key="l.id" :value="l.id">
                            {{ l.partner_name }} — {{ t('partners.owes') }} {{ money(l.outstanding) }}
                        </option>
                    </select>
                    <input v-model="repaymentForm.amount" type="number" step="0.01" :placeholder="t('partners.amount_placeholder')" class="rounded border-stone-300 text-sm">
                    <input v-model="repaymentForm.repaid_at" type="date" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="repaymentForm.processing" class="col-span-3 rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">{{ t('partners.record_repayment_action') }}</button>
                </form>
            </section>
        </main>
    </AppLayout>
</template>
