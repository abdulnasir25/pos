<script setup>
import { ref, watch } from 'vue';
import { usePage, useForm } from '@inertiajs/vue3';

const props = defineProps({
    expenses: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    total: { type: String, default: '0.00' },
});

const page = usePage();
const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

// --- Add category ---------------------------------------------------------

const categoryForm = useForm({ name: '' });

function submitCategory() {
    categoryForm.post('/expenses/categories', { preserveScroll: true, onSuccess: () => categoryForm.reset() });
}

// --- Record expense ---------------------------------------------------------

const expenseForm = useForm({
    expense_category_id: props.categories[0]?.id ?? null,
    amount: '',
    expense_date: new Date().toISOString().slice(0, 10),
    payment_method_id: props.paymentMethods[0]?.id ?? null,
    description: '',
});

function submitExpense() {
    expenseForm.post('/expenses', { preserveScroll: true, onSuccess: () => expenseForm.reset('amount', 'description') });
}

// useForm()'s initial values are a one-time snapshot — they don't
// update on their own when `categories` arrives later via an Inertia
// reload (e.g. right after adding the first one), leaving the select
// bound to null with nothing visibly wrong until submit fails.
watch(
    () => props.categories,
    (categories) => {
        if (expenseForm.expense_category_id === null && categories.length > 0) {
            expenseForm.expense_category_id = categories[0].id;
        }
    },
);

// --- Correction -----------------------------------------------------------------

const correctionForm = useForm({
    expense_id: props.expenses[0]?.id ?? null,
    amount: '',
    description: '',
});

function submitCorrection() {
    correctionForm.post('/expenses/corrections', { preserveScroll: true, onSuccess: () => correctionForm.reset('amount', 'description') });
}

// Same reactivity gap as the category watcher above: the very first
// expense recorded arrives via a reload after this form's initial
// (empty) snapshot, so its select would stay bound to null.
watch(
    () => props.expenses,
    (expenses) => {
        if (correctionForm.expense_id === null && expenses.length > 0) {
            correctionForm.expense_id = expenses[0].id;
        }
    },
);

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);
}
</script>

<template>
    <div class="min-h-screen bg-stone-100">
        <header class="flex items-center justify-between border-b border-stone-200 bg-white px-6 py-4">
            <h1 class="text-lg font-semibold text-stone-900">Expenses</h1>
            <span class="text-sm text-stone-500">{{ page.props.auth.user?.name }}</span>
        </header>

        <div v-if="page.props.flash?.success" class="mx-6 mt-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ page.props.flash.success }}
        </div>
        <div v-if="Object.keys(page.props.errors ?? {}).length" class="mx-6 mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p v-for="(msg, key) in page.props.errors" :key="key">{{ msg }}</p>
        </div>

        <main class="mx-auto max-w-3xl space-y-6 p-6">
            <!-- Record expense -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">Record Expense</h2>
                    <button type="button" @click="toggle('category')" class="text-sm text-stone-600 underline">+ New Category</button>
                </div>

                <form v-if="activePanel === 'category'" @submit.prevent="submitCategory" class="mb-4 flex gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="categoryForm.name" type="text" placeholder="Category name (e.g. Rent)" class="flex-1 rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="categoryForm.processing" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Add</button>
                </form>

                <form v-if="categories.length > 0" @submit.prevent="submitExpense" class="grid grid-cols-2 gap-2">
                    <select v-model="expenseForm.expense_category_id" class="rounded border-stone-300 text-sm">
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <select v-model="expenseForm.payment_method_id" class="rounded border-stone-300 text-sm">
                        <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                    <input v-model="expenseForm.amount" type="number" step="0.01" placeholder="Amount" class="rounded border-stone-300 text-sm">
                    <input v-model="expenseForm.expense_date" type="date" class="rounded border-stone-300 text-sm">
                    <input v-model="expenseForm.description" type="text" placeholder="Description (optional)" class="col-span-2 rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="expenseForm.processing" class="col-span-2 rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Record Expense</button>
                </form>
                <p v-else class="text-sm text-stone-400">Add a category first.</p>
            </section>

            <!-- Correction -->
            <section v-if="expenses.length > 0" class="rounded-lg bg-white p-4 shadow-sm">
                <button type="button" @click="toggle('correction')" class="text-base font-medium text-stone-900">
                    Record Correction {{ activePanel === 'correction' ? '▲' : '▼' }}
                </button>
                <p v-if="activePanel === 'correction'" class="mt-1 text-xs text-stone-500">
                    A negative amount reduces a previous expense (e.g. a refund); the original row is never edited.
                </p>

                <form v-if="activePanel === 'correction'" @submit.prevent="submitCorrection" class="mt-3 grid grid-cols-3 gap-2">
                    <select v-model="correctionForm.expense_id" class="rounded border-stone-300 text-sm">
                        <option v-for="e in expenses" :key="e.id" :value="e.id">
                            {{ e.category }} — {{ money(e.amount) }} ({{ e.expense_date }})
                        </option>
                    </select>
                    <input v-model="correctionForm.amount" type="number" step="0.01" placeholder="Amount (e.g. -500)" class="rounded border-stone-300 text-sm">
                    <input v-model="correctionForm.description" type="text" placeholder="Reason (optional)" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="correctionForm.processing" class="col-span-3 rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Save Correction</button>
                </form>
            </section>

            <!-- History -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">Recent Expenses</h2>
                    <span class="text-sm text-stone-600">Total: <span class="font-medium tabular-nums">{{ money(total) }}</span></span>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in expenses" :key="e.id" class="border-b border-stone-100">
                            <td class="py-2 text-stone-600">{{ e.expense_date }}</td>
                            <td class="text-stone-900">
                                {{ e.category }}
                                <span v-if="e.is_correction" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">correction</span>
                            </td>
                            <td class="text-stone-500">{{ e.description ?? '—' }}</td>
                            <td class="text-right tabular-nums" :class="parseFloat(e.amount) < 0 ? 'text-emerald-700' : 'text-stone-900'">
                                {{ money(e.amount) }}
                            </td>
                        </tr>
                        <tr v-if="expenses.length === 0">
                            <td colspan="4" class="py-4 text-center text-stone-400">No expenses recorded yet.</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
</template>
