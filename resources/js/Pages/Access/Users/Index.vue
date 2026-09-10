<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PlusIcon from '../../../Components/icons/PlusIcon.vue';
import PencilIcon from '../../../Components/icons/PencilIcon.vue';
import TrashIcon from '../../../Components/icons/TrashIcon.vue';
import CheckIcon from '../../../Components/icons/CheckIcon.vue';
import CloseIcon from '../../../Components/icons/CloseIcon.vue';
import { useI18n } from '../../../i18n';

const props = defineProps({
    users: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
});

const { t } = useI18n();
const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

// --- Add user ------------------------------------------------------------

const userForm = useForm({ name: '', email: '', password: '', role_id: null });

function submitUser() {
    userForm.post('/access/users', {
        preserveScroll: true,
        onSuccess: () => { userForm.reset(); toggle(null); },
    });
}

// --- Edit ------------------------------------------------------------------

const editForms = ref({});

function editForm(user) {
    if (!editForms.value[user.id]) {
        editForms.value[user.id] = useForm({ name: user.name, email: user.email, password: '' });
    }
    return editForms.value[user.id];
}

function submitEdit(user) {
    editForm(user).post(`/access/users/${user.id}`, {
        preserveScroll: true,
        onSuccess: () => { editForm(user).reset('password'); toggle(null); },
    });
}

function toggleUserStatus(userId) {
    router.post(`/access/users/${userId}/toggle-status`, {}, { preserveScroll: true });
}

// --- Roles -----------------------------------------------------------------

const roleForms = ref({});

function roleForm(user) {
    if (!roleForms.value[user.id]) {
        const available = props.roles.filter((r) => !user.roles.some((ur) => ur.id === r.id));
        roleForms.value[user.id] = useForm({ role_id: available[0]?.id ?? null });
    }
    return roleForms.value[user.id];
}

function availableRoles(user) {
    return props.roles.filter((r) => !user.roles.some((ur) => ur.id === r.id));
}

function addRole(user) {
    roleForm(user).post(`/access/users/${user.id}/roles`, {
        preserveScroll: true,
        onSuccess: () => { delete roleForms.value[user.id]; },
    });
}

function removeRole(user, role) {
    router.post(`/access/users/${user.id}/roles/${role.id}/remove`, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="t('users.title')">
        <main class="mx-auto max-w-5xl space-y-6 p-6">
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">{{ t('users.list_title') }}</h2>
                    <button
                        type="button"
                        @click="toggle('user')"
                        :aria-label="t('common.add')"
                        class="flex size-8 items-center justify-center rounded-full bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </div>

                <form v-if="activePanel === 'user'" @submit.prevent="submitUser" class="mb-4 grid grid-cols-1 gap-2 rounded-md border border-stone-200 p-3 sm:grid-cols-4">
                    <input v-model="userForm.name" type="text" :placeholder="t('users.name_placeholder')" class="rounded border-stone-300 text-sm">
                    <input v-model="userForm.email" type="email" :placeholder="t('users.email_placeholder')" class="rounded border-stone-300 text-sm">
                    <input v-model="userForm.password" type="password" :placeholder="t('users.password_placeholder')" class="rounded border-stone-300 text-sm">
                    <select v-model="userForm.role_id" class="rounded border-stone-300 text-sm">
                        <option :value="null">{{ t('users.no_role_option') }}</option>
                        <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                    </select>
                    <button
                        type="submit"
                        :disabled="userForm.processing"
                        :aria-label="t('common.add')"
                        class="flex items-center justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700 sm:col-span-4"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </form>

                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">{{ t('common.name') }}</th><th>{{ t('users.email_placeholder') }}</th><th>{{ t('users.roles_label') }}</th><th>{{ t('common.status') }}</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="u in users" :key="u.id">
                            <tr class="border-b border-stone-100">
                                <td class="py-2 text-stone-900">{{ u.name }}</td>
                                <td class="text-stone-500">{{ u.email }}</td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-1">
                                        <span
                                            v-for="r in u.roles"
                                            :key="r.id"
                                            class="inline-flex items-center gap-1 rounded-full bg-stone-100 py-0.5 pl-2 pr-1 text-xs text-stone-700"
                                        >
                                            {{ r.name }}
                                            <button type="button" @click="removeRole(u, r)" :aria-label="t('common.remove')" class="rounded-full p-0.5 hover:bg-stone-200">
                                                <CloseIcon class="size-2.5" />
                                            </button>
                                        </span>
                                        <span v-if="u.roles.length === 0" class="text-xs text-stone-400">{{ t('users.no_roles') }}</span>
                                        <template v-if="availableRoles(u).length > 0">
                                            <select v-model="roleForm(u).role_id" class="rounded border-stone-300 py-0.5 text-xs">
                                                <option v-for="r in availableRoles(u)" :key="r.id" :value="r.id">{{ r.name }}</option>
                                            </select>
                                            <button type="button" @click="addRole(u)" :aria-label="t('users.add_role')" class="inline-flex size-5 items-center justify-center rounded-full text-indigo-600 hover:bg-indigo-50">
                                                <PlusIcon class="size-3" />
                                            </button>
                                        </template>
                                    </div>
                                </td>
                                <td>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs"
                                        :class="u.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-200 text-stone-600'"
                                    >{{ u.status === 'active' ? t('common.active') : t('common.inactive') }}</span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <button
                                        type="button"
                                        @click="toggle(`edit-${u.id}`)"
                                        :aria-label="t('common.edit')"
                                        class="mr-1 inline-flex size-7 items-center justify-center rounded-full text-indigo-600 hover:bg-indigo-50"
                                    >
                                        <PencilIcon class="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="toggleUserStatus(u.id)"
                                        :aria-label="u.status === 'active' ? t('common.deactivate') : t('common.activate')"
                                        class="inline-flex size-7 items-center justify-center rounded-full"
                                        :class="u.status === 'active' ? 'text-red-600 hover:bg-red-50' : 'text-emerald-600 hover:bg-emerald-50'"
                                    >
                                        <TrashIcon v-if="u.status === 'active'" class="size-3.5" />
                                        <CheckIcon v-else class="size-3.5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="activePanel === `edit-${u.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="5" class="p-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <input v-model="editForm(u).name" type="text" :placeholder="t('users.name_placeholder')" class="rounded border-stone-300 text-sm">
                                        <input v-model="editForm(u).email" type="email" :placeholder="t('users.email_placeholder')" class="rounded border-stone-300 text-sm">
                                        <input v-model="editForm(u).password" type="password" :placeholder="t('users.password_placeholder')" class="rounded border-stone-300 text-sm">
                                        <button type="button" @click="submitEdit(u)" :disabled="editForm(u).processing" class="ml-auto rounded-md bg-indigo-600 px-3 py-1.5 text-xs text-white hover:bg-indigo-700">{{ t('common.save') }}</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                </div>
                <p v-if="users.length === 0" class="text-sm text-stone-400">{{ t('users.none_yet') }}</p>
            </section>
        </main>
    </AppLayout>
</template>
