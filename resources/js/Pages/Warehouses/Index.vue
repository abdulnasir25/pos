<script setup>
import { usePage, useForm } from '@inertiajs/vue3';

defineProps({
    warehouses: { type: Array, default: () => [] },
});

const page = usePage();

const form = useForm({ name: '' });

function submit() {
    form.post('/warehouses', { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

<template>
    <div class="min-h-screen bg-stone-100">
        <header class="flex items-center justify-between border-b border-stone-200 bg-white px-6 py-4">
            <h1 class="text-lg font-semibold text-stone-900">Warehouses</h1>
            <span class="text-sm text-stone-500">{{ page.props.auth.user?.name }}</span>
        </header>

        <div v-if="page.props.flash?.success" class="mx-6 mt-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ page.props.flash.success }}
        </div>
        <div v-if="Object.keys(page.props.errors ?? {}).length" class="mx-6 mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p v-for="(msg, key) in page.props.errors" :key="key">{{ msg }}</p>
        </div>

        <main class="mx-auto max-w-2xl space-y-6 p-6">
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">Add Warehouse</h2>
                <form @submit.prevent="submit" class="flex gap-2">
                    <input v-model="form.name" type="text" placeholder="Name (e.g. Main Store)" class="flex-1 rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 disabled:opacity-50">Add</button>
                </form>
            </section>

            <section class="rounded-lg bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">All Warehouses</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Name</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="w in warehouses" :key="w.id" class="border-b border-stone-100">
                            <td class="py-2 text-stone-900">{{ w.name }}</td>
                            <td>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="w.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-200 text-stone-600'"
                                >{{ w.status }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="warehouses.length === 0" class="text-sm text-stone-400">No warehouses yet.</p>
            </section>
        </main>
    </div>
</template>
