<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LandlordLayout from '../../../Layouts/LandlordLayout.vue';

defineProps({
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
                            <th class="py-2">Name</th><th>Slug</th><th>Interval</th><th class="text-right">Price</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in plans" :key="p.id" class="border-b border-stone-100">
                            <td class="py-2 text-stone-900">{{ p.name }}</td>
                            <td class="text-stone-500">{{ p.slug }}</td>
                            <td class="text-stone-500">{{ p.billing_interval }}</td>
                            <td class="text-right tabular-nums">{{ money(p.price) }}</td>
                            <td class="text-stone-500">{{ p.status }}</td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <p v-if="plans.length === 0" class="text-sm text-stone-400">No plans yet.</p>
            </section>
        </main>
    </LandlordLayout>
</template>
