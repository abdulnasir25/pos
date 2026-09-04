<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PlusIcon from '../../Components/icons/PlusIcon.vue';
import PencilIcon from '../../Components/icons/PencilIcon.vue';
import TrashIcon from '../../Components/icons/TrashIcon.vue';
import CheckIcon from '../../Components/icons/CheckIcon.vue';
import { useI18n } from '../../i18n';

defineProps({
    warehouses: { type: Array, default: () => [] },
});

const { t } = useI18n();
const editingId = ref(null);
const editForms = ref({});

const form = useForm({ name: '' });

function submit() {
    form.post('/warehouses', { preserveScroll: true, onSuccess: () => form.reset() });
}

function editForm(warehouse) {
    if (!editForms.value[warehouse.id]) {
        editForms.value[warehouse.id] = useForm({ name: warehouse.name });
    }
    return editForms.value[warehouse.id];
}

function startEdit(warehouse) {
    editingId.value = editingId.value === warehouse.id ? null : warehouse.id;
}

function submitEdit(warehouse) {
    editForm(warehouse).post(`/warehouses/${warehouse.id}`, {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; },
    });
}

function toggleStatus(warehouse) {
    router.post(`/warehouses/${warehouse.id}/toggle-status`, {}, { preserveScroll: true });
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
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">{{ t('common.name') }}</th><th>{{ t('common.status') }}</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="w in warehouses" :key="w.id">
                            <tr class="border-b border-stone-100">
                                <td class="py-2 text-stone-900">{{ w.name }}</td>
                                <td>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs"
                                        :class="w.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-200 text-stone-600'"
                                    >{{ w.status === 'active' ? t('common.active') : t('common.inactive') }}</span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" @click="startEdit(w)" :aria-label="t('common.edit')" class="mr-1 inline-flex size-7 items-center justify-center rounded-full text-indigo-600 hover:bg-indigo-50">
                                        <PencilIcon class="size-3.5" />
                                    </button>
                                    <button
                                        v-if="w.status === 'active'"
                                        type="button"
                                        @click="toggleStatus(w)"
                                        :aria-label="t('common.deactivate')"
                                        class="inline-flex size-7 items-center justify-center rounded-full text-red-600 hover:bg-red-50"
                                    >
                                        <TrashIcon class="size-3.5" />
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        @click="toggleStatus(w)"
                                        :aria-label="t('common.activate')"
                                        class="inline-flex size-7 items-center justify-center rounded-full text-emerald-600 hover:bg-emerald-50"
                                    >
                                        <CheckIcon class="size-3.5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="editingId === w.id" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="3" class="p-2">
                                    <div class="flex items-center gap-2">
                                        <input v-model="editForm(w).name" type="text" class="rounded border-stone-300 text-sm">
                                        <button type="button" @click="submitEdit(w)" :disabled="editForm(w).processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs text-white hover:bg-indigo-700">{{ t('common.save') }}</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                </div>
                <p v-if="warehouses.length === 0" class="text-sm text-stone-400">{{ t('warehouses.none_yet') }}</p>
            </section>
        </main>
    </AppLayout>
</template>
