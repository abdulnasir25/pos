<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PlusIcon from '../../Components/icons/PlusIcon.vue';
import { useI18n } from '../../i18n';

defineProps({
    warehouses: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = useForm({ name: '' });

function submit() {
    form.post('/warehouses', { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

<template>
    <AppLayout :title="t('warehouses.title')">
        <main class="mx-auto max-w-2xl space-y-6 p-6">
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">{{ t('warehouses.add_title') }}</h2>
                <form @submit.prevent="submit" class="flex gap-2">
                    <input v-model="form.name" type="text" :placeholder="t('warehouses.name_placeholder')" class="flex-1 rounded border-stone-300 text-sm">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        :aria-label="t('common.add')"
                        class="flex size-10 flex-shrink-0 items-center justify-center rounded-md bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <PlusIcon class="size-5" />
                    </button>
                </form>
            </section>

            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">{{ t('warehouses.all') }}</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">{{ t('common.name') }}</th><th>{{ t('common.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="w in warehouses" :key="w.id" class="border-b border-stone-100">
                            <td class="py-2 text-stone-900">{{ w.name }}</td>
                            <td>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="w.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-200 text-stone-600'"
                                >{{ w.status === 'active' ? t('common.active') : t('common.inactive') }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="warehouses.length === 0" class="text-sm text-stone-400">{{ t('warehouses.none_yet') }}</p>
            </section>
        </main>
    </AppLayout>
</template>
