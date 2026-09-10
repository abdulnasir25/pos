<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import LandlordLayout from '../../../Layouts/LandlordLayout.vue';
import PencilIcon from '../../../Components/icons/PencilIcon.vue';
import TrashIcon from '../../../Components/icons/TrashIcon.vue';
import CheckIcon from '../../../Components/icons/CheckIcon.vue';

const props = defineProps({
    plans: { type: Array, default: () => [] },
});

const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);
}

const planForm = useForm({ name: '', slug: '', price: '', billing_interval: 'monthly' });

function submitPlan() {
    planForm.post('/landlord/billing/plans', {
        preserveScroll: true,
        onSuccess: () => { planForm.reset(); toggle(null); },
    });
}

// --- Edit / retire ---------------------------------------------------------

const editForms = ref({});

function editForm(plan) {
    if (!editForms.value[plan.id]) {
        editForms.value[plan.id] = useForm({ name: plan.name });
    }
    return editForms.value[plan.id];
}

function submitEdit(plan) {
    editForm(plan).post(`/landlord/billing/plans/${plan.id}`, {
        preserveScroll: true,
        onSuccess: () => toggle(null),
    });
}

function togglePlanStatus(planId) {
    router.post(`/landlord/billing/plans/${planId}/toggle-status`, {}, { preserveScroll: true });
}
</script>

<template>
    <LandlordLayout title="Plans">
        <main class="mx-auto max-w-5xl space-y-6 p-6">
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">Plans</h2>
                    <button type="button" @click="toggle('plan')" class="text-sm text-indigo-700 underline hover:text-indigo-800">+ Add Plan</button>
                </div>

                <form v-if="activePanel === 'plan'" @submit.prevent="submitPlan" class="mb-4 grid grid-cols-1 gap-2 rounded-md border border-stone-200 p-3 sm:grid-cols-4">
                    <input v-model="planForm.name" type="text" placeholder="Name" class="rounded border-stone-300 text-sm">
                    <input v-model="planForm.slug" type="text" placeholder="Slug (e.g. pro)" class="rounded border-stone-300 text-sm">
                    <input v-model="planForm.price" type="number" step="0.01" placeholder="Price" class="rounded border-stone-300 text-sm">
                    <select v-model="planForm.billing_interval" class="rounded border-stone-300 text-sm">
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                    <button type="submit" :disabled="planForm.processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700 sm:col-span-4">Add</button>
                </form>

                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Name</th><th>Slug</th><th>Interval</th><th class="text-right">Price</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="p in plans" :key="p.id">
                            <tr class="border-b border-stone-100">
                                <td class="py-2 text-stone-900">{{ p.name }}</td>
                                <td class="text-stone-500">{{ p.slug }}</td>
                                <td class="text-stone-500">{{ p.billing_interval }}</td>
                                <td class="text-right tabular-nums">{{ money(p.price) }}</td>
                                <td>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs"
                                        :class="p.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-200 text-stone-600'"
                                    >{{ p.status }}</span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <button
                                        type="button"
                                        @click="toggle(`edit-${p.id}`)"
                                        aria-label="Edit"
                                        class="mr-1 inline-flex size-7 items-center justify-center rounded-full text-indigo-600 hover:bg-indigo-50"
                                    >
                                        <PencilIcon class="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="togglePlanStatus(p.id)"
                                        :aria-label="p.status === 'active' ? 'Retire' : 'Reactivate'"
                                        class="inline-flex size-7 items-center justify-center rounded-full"
                                        :class="p.status === 'active' ? 'text-red-600 hover:bg-red-50' : 'text-emerald-600 hover:bg-emerald-50'"
                                    >
                                        <TrashIcon v-if="p.status === 'active'" class="size-3.5" />
                                        <CheckIcon v-else class="size-3.5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="activePanel === `edit-${p.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="6" class="p-2">
                                    <div class="flex items-center gap-2">
                                        <input v-model="editForm(p).name" type="text" placeholder="Name" class="rounded border-stone-300 text-sm">
                                        <span class="text-xs text-stone-400">Price, slug, and interval can't change once a plan exists — retire it and add a new one instead.</span>
                                        <button type="button" @click="submitEdit(p)" :disabled="editForm(p).processing" class="ml-auto rounded-md bg-indigo-600 px-3 py-1.5 text-xs text-white hover:bg-indigo-700">Save</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                </div>
                <p v-if="plans.length === 0" class="text-sm text-stone-400">No plans yet.</p>
            </section>
        </main>
    </LandlordLayout>
</template>
