<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useI18n } from '../../i18n';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    actions: { type: Array, default: () => [] },
    entries: { type: Array, default: () => [] },
});

const { t } = useI18n();
const actionFilter = ref(props.filters.action ?? '');
const expandedId = ref(null);

function applyFilter() {
    router.get('/audit-log', { action: actionFilter.value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
}

function toggleExpand(id) {
    expandedId.value = expandedId.value === id ? null : id;
}
</script>

<template>
    <AppLayout :title="t('audit_log.title')">
        <main class="mx-auto max-w-4xl space-y-4 p-6">
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">{{ t('audit_log.recent') }}</h2>
                    <div class="flex items-center gap-2">
                        <select v-model="actionFilter" class="rounded border-stone-300 text-sm">
                            <option value="">{{ t('audit_log.all_actions') }}</option>
                            <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
                        </select>
                        <button type="button" @click="applyFilter" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">{{ t('audit_log.filter') }}</button>
                    </div>
                </div>

                <p class="mb-3 text-xs text-stone-400">{{ t('audit_log.note') }}</p>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">{{ t('audit_log.when') }}</th><th>{{ t('audit_log.user') }}</th><th>{{ t('audit_log.action') }}</th><th>{{ t('audit_log.entity') }}</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="e in entries" :key="e.id">
                            <tr class="border-b border-stone-100">
                                <td class="py-2 text-stone-500">{{ e.created_at }}</td>
                                <td>{{ e.user }}</td>
                                <td><span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-700">{{ e.action }}</span></td>
                                <td class="text-stone-500">{{ e.auditable ?? '—' }}</td>
                                <td class="text-right">
                                    <button
                                        v-if="e.old_values || e.new_values"
                                        type="button"
                                        @click="toggleExpand(e.id)"
                                        class="text-xs text-indigo-700 underline hover:text-indigo-800"
                                    >
                                        {{ expandedId === e.id ? t('audit_log.hide') : t('audit_log.details') }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="expandedId === e.id" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="5" class="px-2 py-3">
                                    <div class="grid grid-cols-2 gap-4 text-xs">
                                        <div v-if="e.old_values">
                                            <p class="mb-1 uppercase text-stone-400">{{ t('audit_log.before') }}</p>
                                            <pre class="whitespace-pre-wrap rounded bg-white p-2">{{ JSON.stringify(e.old_values, null, 2) }}</pre>
                                        </div>
                                        <div v-if="e.new_values">
                                            <p class="mb-1 uppercase text-stone-400">{{ t('audit_log.after') }}</p>
                                            <pre class="whitespace-pre-wrap rounded bg-white p-2">{{ JSON.stringify(e.new_values, null, 2) }}</pre>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="entries.length === 0"><td colspan="5" class="py-3 text-center text-stone-400">{{ t('audit_log.none_yet') }}</td></tr>
                    </tbody>
                </table>
            </section>
        </main>
    </AppLayout>
</template>
