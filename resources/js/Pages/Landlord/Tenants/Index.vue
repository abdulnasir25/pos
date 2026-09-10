<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import LandlordLayout from '../../../Layouts/LandlordLayout.vue';
import PlusIcon from '../../../Components/icons/PlusIcon.vue';
import PencilIcon from '../../../Components/icons/PencilIcon.vue';
import TrashIcon from '../../../Components/icons/TrashIcon.vue';
import CheckIcon from '../../../Components/icons/CheckIcon.vue';

defineProps({
    tenants: { type: Array, default: () => [] },
});

const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

const tenantForm = useForm({ name: '', slug: '', owner_name: '', owner_email: '' });

function submitTenant() {
    tenantForm.post('/landlord/tenants', {
        preserveScroll: true,
        onSuccess: () => { tenantForm.reset(); toggle(null); },
    });
}

function toggleTenantStatus(tenantId) {
    router.post(`/landlord/tenants/${tenantId}/toggle-status`, {}, { preserveScroll: true });
}

// --- Edit --------------------------------------------------------------

const editForms = ref({});

function editForm(tenant) {
    if (!editForms.value[tenant.id]) {
        editForms.value[tenant.id] = useForm({ name: tenant.name });
    }
    return editForms.value[tenant.id];
}

function submitEdit(tenant) {
    editForm(tenant).post(`/landlord/tenants/${tenant.id}`, {
        preserveScroll: true,
        onSuccess: () => toggle(null),
    });
}
</script>

<template>
    <LandlordLayout title="Tenants">
        <main class="mx-auto max-w-5xl space-y-6 p-6">
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">Tenants</h2>
                    <button
                        type="button"
                        @click="toggle('tenant')"
                        aria-label="Add a shop"
                        class="flex size-8 items-center justify-center rounded-full bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </div>

                <form v-if="activePanel === 'tenant'" @submit.prevent="submitTenant" class="mb-4 grid grid-cols-1 gap-2 rounded-md border border-stone-200 p-3 sm:grid-cols-2">
                    <input v-model="tenantForm.name" type="text" placeholder="Shop name (e.g. Al-Fateh Cloth House)" class="rounded border-stone-300 text-sm">
                    <input v-model="tenantForm.slug" type="text" placeholder="Subdomain (optional, e.g. alfateh)" class="rounded border-stone-300 text-sm">
                    <input v-model="tenantForm.owner_name" type="text" placeholder="Owner's name" class="rounded border-stone-300 text-sm">
                    <input v-model="tenantForm.owner_email" type="email" placeholder="Owner's email" class="rounded border-stone-300 text-sm">
                    <button
                        type="submit"
                        :disabled="tenantForm.processing"
                        aria-label="Add"
                        class="flex items-center justify-center gap-2 rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700 sm:col-span-2"
                    >
                        <PlusIcon class="size-4" /> Provision shop
                    </button>
                </form>

                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Name</th><th>Subdomain</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="t in tenants" :key="t.id">
                            <tr class="border-b border-stone-100">
                                <td class="py-2 text-stone-900">{{ t.name }}</td>
                                <td class="text-stone-500">{{ t.url }}</td>
                                <td>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs"
                                        :class="t.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'"
                                    >{{ t.status }}</span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <button
                                        type="button"
                                        @click="toggle(`edit-${t.id}`)"
                                        aria-label="Edit"
                                        class="mr-1 inline-flex size-7 items-center justify-center rounded-full text-indigo-600 hover:bg-indigo-50"
                                    >
                                        <PencilIcon class="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="toggleTenantStatus(t.id)"
                                        :aria-label="t.status === 'active' ? 'Suspend' : 'Reactivate'"
                                        class="inline-flex size-7 items-center justify-center rounded-full"
                                        :class="t.status === 'active' ? 'text-red-600 hover:bg-red-50' : 'text-emerald-600 hover:bg-emerald-50'"
                                    >
                                        <TrashIcon v-if="t.status === 'active'" class="size-3.5" />
                                        <CheckIcon v-else class="size-3.5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="activePanel === `edit-${t.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="4" class="p-2">
                                    <div class="flex items-center gap-2">
                                        <input v-model="editForm(t).name" type="text" placeholder="Shop name" class="rounded border-stone-300 text-sm">
                                        <span class="text-xs text-stone-400">The subdomain ({{ t.url }}) can't change once a shop exists.</span>
                                        <button type="button" @click="submitEdit(t)" :disabled="editForm(t).processing" class="ml-auto rounded-md bg-indigo-600 px-3 py-1.5 text-xs text-white hover:bg-indigo-700">Save</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                </div>
                <p v-if="tenants.length === 0" class="text-sm text-stone-400">No tenants yet.</p>
            </section>
        </main>
    </LandlordLayout>
</template>
