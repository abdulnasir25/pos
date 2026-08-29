<script setup>
import { ref, watch } from 'vue';
import { usePage, useForm } from '@inertiajs/vue3';

const props = defineProps({
    employees: { type: Array, default: () => [] },
    financialPeriods: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
});

const page = usePage();
const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

function money(n) {
    if (n === null || n === undefined) return '—';
    return (Math.round(parseFloat(n) * 100) / 100).toFixed(2);
}

// --- Add employee ------------------------------------------------------

const employeeForm = useForm({ name: '', phone: '', hired_at: new Date().toISOString().slice(0, 10) });

function submitEmployee() {
    employeeForm.post('/employees', { preserveScroll: true, onSuccess: () => employeeForm.reset('name', 'phone') });
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
    <div class="min-h-screen bg-stone-100">
        <header class="flex items-center justify-between border-b border-stone-200 bg-white px-6 py-4">
            <h1 class="text-lg font-semibold text-stone-900">Employees</h1>
            <span class="text-sm text-stone-500">{{ page.props.auth.user?.name }}</span>
        </header>

        <div v-if="page.props.flash?.success" class="mx-6 mt-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ page.props.flash.success }}
        </div>
        <div v-if="Object.keys(page.props.errors ?? {}).length" class="mx-6 mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p v-for="(msg, key) in page.props.errors" :key="key">{{ msg }}</p>
        </div>

        <main class="mx-auto max-w-5xl space-y-6 p-6">
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">Employees</h2>
                    <button type="button" @click="toggle('employee')" class="text-sm text-stone-600 underline">+ Add Employee</button>
                </div>

                <form v-if="activePanel === 'employee'" @submit.prevent="submitEmployee" class="mb-4 grid grid-cols-4 gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="employeeForm.name" type="text" placeholder="Name" class="rounded border-stone-300 text-sm">
                    <input v-model="employeeForm.phone" type="text" placeholder="Phone (optional)" class="rounded border-stone-300 text-sm">
                    <input v-model="employeeForm.hired_at" type="date" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="employeeForm.processing" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Add</button>
                </form>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Name</th><th>Phone</th><th>Hired</th><th class="text-right">Salary</th><th>Status</th><th></th>
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
                                    >{{ e.status }}</span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" @click="toggle(`salary-${e.id}`)" class="mr-2 text-xs text-stone-600 underline">Salary</button>
                                    <button type="button" @click="toggle(`pay-${e.id}`)" class="mr-2 text-xs text-stone-600 underline">Pay</button>
                                    <button type="button" @click="toggle(`status-${e.id}`)" class="text-xs text-stone-600 underline">Status</button>
                                </td>
                            </tr>

                            <tr v-if="activePanel === `salary-${e.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="6" class="p-2">
                                    <div class="flex items-center gap-2">
                                        <input v-model="salaryForm(e.id).monthly_salary" type="number" step="0.01" placeholder="Monthly salary" class="w-32 rounded border-stone-300 text-sm">
                                        <span class="text-xs text-stone-500">effective from</span>
                                        <input v-model="salaryForm(e.id).effective_from" type="date" class="rounded border-stone-300 text-sm">
                                        <button type="button" @click="submitSalary(e.id)" :disabled="salaryForm(e.id).processing" class="rounded-md bg-stone-900 px-3 py-1.5 text-xs text-white hover:bg-stone-700">Save</button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="activePanel === `pay-${e.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="6" class="p-2">
                                    <div class="flex items-center gap-2">
                                        <select v-model="paymentForm(e.id).financial_period_id" class="rounded border-stone-300 text-sm">
                                            <option v-for="p in financialPeriods" :key="p.id" :value="p.id">{{ p.period_start }} — {{ p.period_end }}</option>
                                        </select>
                                        <input v-model="paymentForm(e.id).amount" type="number" step="0.01" placeholder="Amount" class="w-28 rounded border-stone-300 text-sm">
                                        <select v-model="paymentForm(e.id).payment_method_id" class="rounded border-stone-300 text-sm">
                                            <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                                        </select>
                                        <button type="button" @click="submitPayment(e.id)" :disabled="paymentForm(e.id).processing" class="rounded-md bg-stone-900 px-3 py-1.5 text-xs text-white hover:bg-stone-700">Record</button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="activePanel === `status-${e.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="6" class="p-2">
                                    <div class="flex items-center gap-2">
                                        <select v-model="statusForm(e.id, e.status).status" class="rounded border-stone-300 text-sm">
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="terminated">Terminated</option>
                                        </select>
                                        <template v-if="statusForm(e.id, e.status).status === 'terminated'">
                                            <span class="text-xs text-stone-500">as of</span>
                                            <input v-model="statusForm(e.id, e.status).terminated_at" type="date" class="rounded border-stone-300 text-sm">
                                        </template>
                                        <button type="button" @click="submitStatus(e.id)" :disabled="statusForm(e.id, e.status).processing" class="rounded-md bg-stone-900 px-3 py-1.5 text-xs text-white hover:bg-stone-700">Save</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p v-if="employees.length === 0" class="text-sm text-stone-400">No employees yet.</p>
            </section>
        </main>
    </div>
</template>
