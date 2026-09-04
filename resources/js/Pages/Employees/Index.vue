<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PlusIcon from '../../Components/icons/PlusIcon.vue';
import PencilIcon from '../../Components/icons/PencilIcon.vue';
import { useI18n } from '../../i18n';

const props = defineProps({
    employees: { type: Array, default: () => [] },
    financialPeriods: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
});

const { t } = useI18n();
const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

function money(n) {
    if (n === null || n === undefined) return '—';
    return (Math.round(parseFloat(n) * 100) / 100).toFixed(2);
}

function statusLabel(status) {
    if (status === 'active') return t('common.active');
    if (status === 'inactive') return t('common.inactive');
    return t('employees.terminated');
}

// --- Add employee ------------------------------------------------------

const employeeForm = useForm({ name: '', phone: '', hired_at: new Date().toISOString().slice(0, 10) });

function submitEmployee() {
    employeeForm.post('/employees', { preserveScroll: true, onSuccess: () => employeeForm.reset('name', 'phone') });
}

// --- Profile edit -----------------------------------------------------

const editForms = ref({});

function editForm(employee) {
    if (!editForms.value[employee.id]) {
        editForms.value[employee.id] = useForm({ name: employee.name, phone: employee.phone ?? '' });
    }
    return editForms.value[employee.id];
}

function submitEdit(employee) {
    editForm(employee).post(`/employees/${employee.id}/profile`, {
        preserveScroll: true,
        onSuccess: () => toggle(null),
    });
}

// --- Salary change -------------------------------------------------------

const salaryForms = ref({});

function salaryForm(employeeId) {
    if (!salaryForms.value[employeeId]) {
        salaryForms.value[employeeId] = useForm({
            monthly_salary: '',
            effective_from: new Date().toISOString().slice(0, 10),
            effective_to: '',
        });
    }
    return salaryForms.value[employeeId];
}

function submitSalary(employeeId) {
    salaryForm(employeeId).post(`/employees/${employeeId}/salary`, {
        preserveScroll: true,
        onSuccess: () => { salaryForm(employeeId).reset('monthly_salary', 'effective_to'); toggle(null); },
    });
}

// --- Status change -------------------------------------------------------

const statusForms = ref({});

function statusForm(employeeId, currentStatus) {
    if (!statusForms.value[employeeId]) {
        statusForms.value[employeeId] = useForm({ status: currentStatus, terminated_at: new Date().toISOString().slice(0, 10) });
    }
    return statusForms.value[employeeId];
}

function submitStatus(employeeId) {
    statusForm(employeeId).post(`/employees/${employeeId}/status`, {
        preserveScroll: true,
        onSuccess: () => toggle(null),
    });
}

// --- Salary payment --------------------------------------------------------

const paymentForms = ref({});

function paymentForm(employeeId) {
    if (!paymentForms.value[employeeId]) {
        paymentForms.value[employeeId] = useForm({
            financial_period_id: props.financialPeriods[0]?.id ?? null,
            amount: '',
            payment_method_id: props.paymentMethods[0]?.id ?? null,
        });
    }
    return paymentForms.value[employeeId];
}

watch(() => props.financialPeriods, (list) => {
    if (list.length === 0) return;
    Object.values(paymentForms.value).forEach((f) => {
        if (f.financial_period_id === null) f.financial_period_id = list[0].id;
    });
});
watch(() => props.paymentMethods, (list) => {
    if (list.length === 0) return;
    Object.values(paymentForms.value).forEach((f) => {
        if (f.payment_method_id === null) f.payment_method_id = list[0].id;
    });
});

function submitPayment(employeeId) {
    paymentForm(employeeId).post(`/employees/${employeeId}/salary-payments`, {
        preserveScroll: true,
        onSuccess: () => { paymentForm(employeeId).reset('amount'); toggle(null); },
    });
}
</script>

<template>
    <AppLayout :title="t('employees.title')">
        <main class="mx-auto max-w-5xl space-y-6 p-6">
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">{{ t('employees.list_title') }}</h2>
                    <button
                        type="button"
                        @click="toggle('employee')"
                        :aria-label="t('common.add')"
                        class="flex size-8 items-center justify-center rounded-full bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </div>

                <form v-if="activePanel === 'employee'" @submit.prevent="submitEmployee" class="mb-4 grid grid-cols-4 gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="employeeForm.name" type="text" :placeholder="t('common.name')" class="rounded border-stone-300 text-sm">
                    <input v-model="employeeForm.phone" type="text" :placeholder="t('customers.phone_placeholder')" class="rounded border-stone-300 text-sm">
                    <input v-model="employeeForm.hired_at" type="date" class="rounded border-stone-300 text-sm">
                    <button
                        type="submit"
                        :disabled="employeeForm.processing"
                        :aria-label="t('common.add')"
                        class="flex items-center justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </form>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">{{ t('common.name') }}</th><th>{{ t('common.phone') }}</th><th>{{ t('employees.hired') }}</th><th class="text-right">{{ t('employees.salary') }}</th><th>{{ t('common.status') }}</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="e in employees" :key="e.id">
                            <tr class="border-b border-stone-100">
                                <td class="py-2 text-stone-900">{{ e.name }}</td>
                                <td class="text-stone-500">{{ e.phone ?? '—' }}</td>
                                <td class="text-stone-500">{{ e.hired_at }}</td>
                                <td class="text-right tabular-nums">{{ money(e.current_salary) }}</td>
                                <td>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs"
                                        :class="{
                                            'bg-emerald-100 text-emerald-800': e.status === 'active',
                                            'bg-stone-200 text-stone-600': e.status === 'inactive',
                                            'bg-red-100 text-red-800': e.status === 'terminated',
                                        }"
                                    >{{ statusLabel(e.status) }}</span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <button
                                        type="button"
                                        @click="toggle(`edit-${e.id}`)"
                                        :aria-label="t('common.edit')"
                                        class="mr-2 inline-flex size-6 items-center justify-center rounded-full text-stone-500 hover:bg-stone-100 hover:text-indigo-700"
                                    >
                                        <PencilIcon class="size-3.5" />
                                    </button>
                                    <button type="button" @click="toggle(`salary-${e.id}`)" class="mr-2 text-xs text-indigo-700 underline hover:text-indigo-800">{{ t('employees.salary_action') }}</button>
                                    <button type="button" @click="toggle(`pay-${e.id}`)" class="mr-2 text-xs text-indigo-700 underline hover:text-indigo-800">{{ t('employees.pay_action') }}</button>
                                    <button type="button" @click="toggle(`status-${e.id}`)" class="text-xs text-indigo-700 underline hover:text-indigo-800">{{ t('employees.status_action') }}</button>
                                </td>
                            </tr>

                            <tr v-if="activePanel === `edit-${e.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="6" class="p-2">
                                    <div class="flex items-center gap-2">
                                        <input v-model="editForm(e).name" type="text" :placeholder="t('common.name')" class="rounded border-stone-300 text-sm">
                                        <input v-model="editForm(e).phone" type="text" :placeholder="t('customers.phone_placeholder')" class="rounded border-stone-300 text-sm">
                                        <button type="button" @click="submitEdit(e)" :disabled="editForm(e).processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs text-white hover:bg-indigo-700">{{ t('common.save') }}</button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="activePanel === `salary-${e.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="6" class="p-2">
                                    <div class="flex items-center gap-2">
                                        <input v-model="salaryForm(e.id).monthly_salary" type="number" step="0.01" :placeholder="t('employees.salary')" class="w-32 rounded border-stone-300 text-sm">
                                        <span class="text-xs text-stone-500">{{ t('employees.effective_from') }}</span>
                                        <input v-model="salaryForm(e.id).effective_from" type="date" class="rounded border-stone-300 text-sm">
                                        <button type="button" @click="submitSalary(e.id)" :disabled="salaryForm(e.id).processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs text-white hover:bg-indigo-700">{{ t('common.save') }}</button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="activePanel === `pay-${e.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="6" class="p-2">
                                    <div class="flex items-center gap-2">
                                        <select v-model="paymentForm(e.id).financial_period_id" class="rounded border-stone-300 text-sm">
                                            <option v-for="p in financialPeriods" :key="p.id" :value="p.id">{{ p.period_start }} — {{ p.period_end }}</option>
                                        </select>
                                        <input v-model="paymentForm(e.id).amount" type="number" step="0.01" :placeholder="t('pos.amount')" class="w-28 rounded border-stone-300 text-sm">
                                        <select v-model="paymentForm(e.id).payment_method_id" class="rounded border-stone-300 text-sm">
                                            <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                                        </select>
                                        <button type="button" @click="submitPayment(e.id)" :disabled="paymentForm(e.id).processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs text-white hover:bg-indigo-700">{{ t('employees.record') }}</button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="activePanel === `status-${e.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="6" class="p-2">
                                    <div class="flex items-center gap-2">
                                        <select v-model="statusForm(e.id, e.status).status" class="rounded border-stone-300 text-sm">
                                            <option value="active">{{ t('common.active') }}</option>
                                            <option value="inactive">{{ t('common.inactive') }}</option>
                                            <option value="terminated">{{ t('employees.terminated') }}</option>
                                        </select>
                                        <template v-if="statusForm(e.id, e.status).status === 'terminated'">
                                            <span class="text-xs text-stone-500">{{ t('employees.as_of') }}</span>
                                            <input v-model="statusForm(e.id, e.status).terminated_at" type="date" class="rounded border-stone-300 text-sm">
                                        </template>
                                        <button type="button" @click="submitStatus(e.id)" :disabled="statusForm(e.id, e.status).processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs text-white hover:bg-indigo-700">{{ t('common.save') }}</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p v-if="employees.length === 0" class="text-sm text-stone-400">{{ t('employees.none_yet') }}</p>
            </section>
        </main>
    </AppLayout>
</template>
