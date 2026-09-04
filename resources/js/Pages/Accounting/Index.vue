<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PlusIcon from '../../Components/icons/PlusIcon.vue';
import TrashIcon from '../../Components/icons/TrashIcon.vue';
import ChevronDownIcon from '../../Components/icons/ChevronDownIcon.vue';
import { useI18n } from '../../i18n';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    entries: { type: Array, default: () => [] },
});

const { t } = useI18n();
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
    <AppLayout :title="t('accounting.title')">
        <main class="mx-auto max-w-4xl space-y-6 p-6">
            <!-- Chart of accounts -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">{{ t('accounting.chart_title') }}</h2>
                    <button
                        type="button"
                        @click="toggle('account')"
                        :aria-label="t('common.add')"
                        class="flex size-8 items-center justify-center rounded-full bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </div>

                <form v-if="activePanel === 'account'" @submit.prevent="submitAccount" class="mb-4 grid grid-cols-4 gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="accountForm.code" type="text" :placeholder="t('accounting.code_placeholder')" class="rounded border-stone-300 text-sm">
                    <input v-model="accountForm.name" type="text" :placeholder="t('common.name')" class="rounded border-stone-300 text-sm">
                    <select v-model="accountForm.type" class="rounded border-stone-300 text-sm">
                        <option value="asset">{{ t('accounting.type_asset') }}</option>
                        <option value="liability">{{ t('accounting.type_liability') }}</option>
                        <option value="equity">{{ t('accounting.type_equity') }}</option>
                        <option value="contra_equity">{{ t('accounting.type_contra_equity') }}</option>
                        <option value="revenue">{{ t('accounting.type_revenue') }}</option>
                        <option value="expense">{{ t('accounting.type_expense') }}</option>
                    </select>
                    <select v-model="accountForm.parent_id" class="rounded border-stone-300 text-sm">
                        <option value="">{{ t('accounting.no_parent') }}</option>
                        <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                    </select>
                    <button
                        type="submit"
                        :disabled="accountForm.processing"
                        :aria-label="t('common.add')"
                        class="col-span-4 flex items-center justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </form>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">{{ t('accounting.code') }}</th><th>{{ t('common.name') }}</th><th>{{ t('accounting.type') }}</th><th class="text-right">{{ t('accounting.balance') }}</th>
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
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <button type="button" @click="toggle('entry')" class="flex items-center gap-2 text-base font-medium text-stone-900">
                    {{ t('accounting.post_entry') }}
                    <ChevronDownIcon class="size-4 transition-transform" :class="{ 'rotate-180': activePanel === 'entry' }" />
                </button>

                <form v-if="activePanel === 'entry'" @submit.prevent="submitEntry" class="mt-3">
                    <div class="mb-3 flex gap-2">
                        <input v-model="entryForm.entry_date" type="date" class="rounded border-stone-300 text-sm">
                        <input v-model="entryForm.description" type="text" :placeholder="t('accounting.description_placeholder')" class="flex-1 rounded border-stone-300 text-sm">
                    </div>

                    <div v-for="(line, index) in entryForm.lines" :key="index" class="mb-2 grid grid-cols-4 gap-2">
                        <select v-model="line.account_id" class="col-span-2 rounded border-stone-300 text-sm">
                            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                        </select>
                        <input v-model="line.debit" type="number" step="0.01" :placeholder="t('accounting.debit_placeholder')" class="rounded border-stone-300 text-sm">
                        <div class="flex gap-1">
                            <input v-model="line.credit" type="number" step="0.01" :placeholder="t('accounting.credit_placeholder')" class="w-full rounded border-stone-300 text-sm">
                            <button
                                v-if="entryForm.lines.length > 2"
                                type="button"
                                @click="removeLine(index)"
                                :aria-label="t('common.remove')"
                                class="flex size-8 flex-shrink-0 items-center justify-center rounded text-red-600 hover:bg-red-50"
                            >
                                <TrashIcon class="size-3.5" />
                            </button>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="addLine"
                        :aria-label="t('common.add')"
                        class="flex size-7 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 hover:bg-indigo-200"
                    >
                        <PlusIcon class="size-3.5" />
                    </button>

                    <div class="mt-3 flex items-center justify-between border-t border-stone-200 pt-3 text-sm">
                        <span>{{ t('accounting.debits') }}: <span class="font-medium tabular-nums">{{ money(totalDebits) }}</span> · {{ t('accounting.credits') }}: <span class="font-medium tabular-nums">{{ money(totalCredits) }}</span></span>
                        <span :class="isBalanced ? 'text-emerald-700' : 'text-red-600'">{{ isBalanced ? t('accounting.balanced') : t('accounting.not_balanced') }}</span>
                    </div>

                    <button type="submit" :disabled="!isBalanced || entryForm.processing" class="mt-3 w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        {{ t('accounting.post_entry_action') }}
                    </button>
                </form>
            </section>

            <!-- Recent entries -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">{{ t('accounting.recent_entries') }}</h2>
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
                <p v-if="entries.length === 0" class="text-sm text-stone-400">{{ t('accounting.none_yet') }}</p>
            </section>
        </main>
    </AppLayout>
</template>
