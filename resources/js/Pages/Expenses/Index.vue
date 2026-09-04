<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PlusIcon from '../../Components/icons/PlusIcon.vue';
import ChevronDownIcon from '../../Components/icons/ChevronDownIcon.vue';
import { useI18n } from '../../i18n';

const props = defineProps({
    expenses: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    total: { type: String, default: '0.00' },
});

const { t } = useI18n();
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
    <AppLayout :title="t('expenses.title')">
        <main class="mx-auto max-w-3xl space-y-6 p-6">
            <!-- Record expense -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">{{ t('expenses.record_title') }}</h2>
                    <button
                        type="button"
                        @click="toggle('category')"
                        :aria-label="t('common.add')"
                        class="flex size-8 items-center justify-center rounded-full bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </div>

                <form v-if="activePanel === 'category'" @submit.prevent="submitCategory" class="mb-4 flex gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="categoryForm.name" type="text" :placeholder="t('expenses.category_placeholder')" class="flex-1 rounded border-stone-300 text-sm">
                    <button
                        type="submit"
                        :disabled="categoryForm.processing"
                        :aria-label="t('common.add')"
                        class="flex items-center justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </form>

                <form v-if="categories.length > 0" @submit.prevent="submitExpense" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <select v-model="expenseForm.expense_category_id" class="rounded border-stone-300 text-sm">
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <select v-model="expenseForm.payment_method_id" class="rounded border-stone-300 text-sm">
                        <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                    <input v-model="expenseForm.amount" type="number" step="0.01" :placeholder="t('expenses.amount_placeholder')" class="rounded border-stone-300 text-sm">
                    <input v-model="expenseForm.expense_date" type="date" class="rounded border-stone-300 text-sm">
                    <input v-model="expenseForm.description" type="text" :placeholder="t('expenses.description_placeholder')" class="col-span-2 rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="expenseForm.processing" class="col-span-2 rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">{{ t('expenses.record') }}</button>
                </form>
                <p v-else class="text-sm text-stone-400">{{ t('expenses.add_category_first') }}</p>
            </section>

            <!-- Correction -->
            <section v-if="expenses.length > 0" class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <button type="button" @click="toggle('correction')" class="flex items-center gap-2 text-base font-medium text-stone-900">
                    {{ t('expenses.record_correction') }}
                    <ChevronDownIcon class="size-4 transition-transform" :class="{ 'rotate-180': activePanel === 'correction' }" />
                </button>
                <p v-if="activePanel === 'correction'" class="mt-1 text-xs text-stone-500">
                    {{ t('expenses.correction_note') }}
                </p>

                <form v-if="activePanel === 'correction'" @submit.prevent="submitCorrection" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <select v-model="correctionForm.expense_id" class="rounded border-stone-300 text-sm">
                        <option v-for="e in expenses" :key="e.id" :value="e.id">
                            {{ e.category }} — {{ money(e.amount) }} ({{ e.expense_date }})
                        </option>
                    </select>
                    <input v-model="correctionForm.amount" type="number" step="0.01" :placeholder="t('expenses.correction_amount_placeholder')" class="rounded border-stone-300 text-sm">
                    <input v-model="correctionForm.description" type="text" :placeholder="t('expenses.correction_reason_placeholder')" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="correctionForm.processing" class="col-span-3 rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">{{ t('expenses.save_correction') }}</button>
                </form>
            </section>

            <!-- History -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">{{ t('expenses.recent') }}</h2>
                    <span class="text-sm text-stone-600">{{ t('expenses.total') }}: <span class="font-medium tabular-nums">{{ money(total) }}</span></span>
                </div>

                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">{{ t('expenses.date') }}</th>
                            <th>{{ t('expenses.category') }}</th>
                            <th>{{ t('expenses.description') }}</th>
                            <th class="text-right">{{ t('expenses.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in expenses" :key="e.id" class="border-b border-stone-100">
                            <td class="py-2 text-stone-600">{{ e.expense_date }}</td>
                            <td class="text-stone-900">
                                {{ e.category }}
                                <span v-if="e.is_correction" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{{ t('expenses.correction_tag') }}</span>
                            </td>
                            <td class="text-stone-500">{{ e.description ?? '—' }}</td>
                            <td class="text-right tabular-nums" :class="parseFloat(e.amount) < 0 ? 'text-emerald-700' : 'text-stone-900'">
                                {{ money(e.amount) }}
                            </td>
                        </tr>
                        <tr v-if="expenses.length === 0">
                            <td colspan="4" class="py-4 text-center text-stone-400">{{ t('expenses.none_yet') }}</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
