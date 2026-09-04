<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    periods: { type: Array, default: () => [] },
});

const showForm = ref(false);

const form = useForm({ period_start: '', period_end: '' });

function submit() {
    form.post('/financial-periods', { preserveScroll: true, onSuccess: () => { form.reset(); showForm.value = false; } });
}

function advance(period) {
    const routes = {
        open: `/financial-periods/${period.id}/calculation`,
        calculating: `/financial-periods/${period.id}/review`,
        under_review: `/financial-periods/${period.id}/close`,
    };
    const url = routes[period.status];
    if (url) router.post(url, {}, { preserveScroll: true });
}

const nextActionLabel = {
    open: 'Start calculation',
    calculating: 'Move to review',
    under_review: 'Close period',
};

const statusLabel = {
    open: 'Open',
    calculating: 'Calculating',
    under_review: 'Under review',
    closed: 'Closed',
};

const statusClass = {
    open: 'bg-stone-100 text-stone-700',
    calculating: 'bg-amber-100 text-amber-800',
    under_review: 'bg-blue-100 text-blue-800',
    closed: 'bg-emerald-100 text-emerald-800',
};
</script>

<template>
    <AppLayout title="Financial Periods">
        <main class="mx-auto max-w-4xl space-y-6 p-6">
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">Periods</h2>
                    <button type="button" @click="showForm = !showForm" class="text-sm text-indigo-700 underline hover:text-indigo-800">+ New Period</button>
                </div>

                <form v-if="showForm" @submit.prevent="submit" class="mb-4 grid grid-cols-3 gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="form.period_start" type="date" class="rounded border-stone-300 text-sm">
                    <input v-model="form.period_end" type="date" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">Create</button>
                </form>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Period</th><th>Status</th><th>Calculated</th><th>Reviewed by</th><th>Closed</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in periods" :key="p.id" class="border-b border-stone-100">
                            <td class="py-2 text-stone-900">{{ p.period_start }} — {{ p.period_end }}</td>
                            <td>
                                <span class="rounded-full px-2 py-0.5 text-xs" :class="statusClass[p.status]">{{ statusLabel[p.status] }}</span>
                            </td>
                            <td class="text-stone-500">{{ p.calculated_at ?? '—' }}</td>
                            <td class="text-stone-500">{{ p.reviewed_by ?? '—' }}</td>
                            <td class="text-stone-500">{{ p.closed_at ?? '—' }}</td>
                            <td class="text-right">
                                <button
                                    v-if="nextActionLabel[p.status]"
                                    type="button"
                                    @click="advance(p)"
                                    class="text-xs text-indigo-700 underline hover:text-indigo-800"
                                >{{ nextActionLabel[p.status] }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="periods.length === 0" class="text-sm text-stone-400">No financial periods yet.</p>
            </section>
        </main>
    </AppLayout>
</template>
