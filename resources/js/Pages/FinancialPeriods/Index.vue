<script setup>
import { ref } from 'vue';
import { usePage, useForm, router } from '@inertiajs/vue3';

defineProps({
    periods: { type: Array, default: () => [] },
});

const page = usePage();
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
    <div class="min-h-screen bg-stone-100">
        <header class="flex items-center justify-between border-b border-stone-200 bg-white px-6 py-4">
            <h1 class="text-lg font-semibold text-stone-900">Financial Periods</h1>
            <span class="text-sm text-stone-500">{{ page.props.auth.user?.name }}</span>
        </header>

        <div v-if="page.props.flash?.success" class="mx-6 mt-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ page.props.flash.success }}
        </div>
        <div v-if="Object.keys(page.props.errors ?? {}).length" class="mx-6 mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p v-for="(msg, key) in page.props.errors" :key="key">{{ msg }}</p>
        </div>

        <main class="mx-auto max-w-4xl space-y-6 p-6">
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">Periods</h2>
                    <button type="button" @click="showForm = !showForm" class="text-sm text-stone-600 underline">+ New Period</button>
                </div>

                <form v-if="showForm" @submit.prevent="submit" class="mb-4 grid grid-cols-3 gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="form.period_start" type="date" class="rounded border-stone-300 text-sm">
                    <input v-model="form.period_end" type="date" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Create</button>
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
                                    class="text-xs text-stone-600 underline"
                                >{{ nextActionLabel[p.status] }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="periods.length === 0" class="text-sm text-stone-400">No financial periods yet.</p>
            </section>
        </main>
    </div>
</template>
