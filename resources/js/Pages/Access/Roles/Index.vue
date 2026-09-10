<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PlusIcon from '../../../Components/icons/PlusIcon.vue';
import ChevronDownIcon from '../../../Components/icons/ChevronDownIcon.vue';
import { useI18n } from '../../../i18n';

defineProps({
    roles: { type: Array, default: () => [] },
    permissions: { type: Array, default: () => [] },
});

const { t } = useI18n();
const activePanel = ref(null);
const expandedRoleId = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

function toggleExpanded(roleId) {
    expandedRoleId.value = expandedRoleId.value === roleId ? null : roleId;
}

const roleForm = useForm({ name: '' });

function submitRole() {
    roleForm.post('/access/roles', {
        preserveScroll: true,
        onSuccess: () => { roleForm.reset(); toggle(null); },
    });
}

function togglePermission(role, permission) {
    if (role.is_protected) return;
    router.post(`/access/roles/${role.id}/permissions/${permission.id}/toggle`, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="t('roles.title')">
        <main class="mx-auto max-w-4xl space-y-6 p-6">
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">{{ t('roles.list_title') }}</h2>
                    <button
                        type="button"
                        @click="toggle('role')"
                        :aria-label="t('common.add')"
                        class="flex size-8 items-center justify-center rounded-full bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </div>

                <form v-if="activePanel === 'role'" @submit.prevent="submitRole" class="mb-4 flex gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="roleForm.name" type="text" :placeholder="t('roles.name_placeholder')" class="flex-1 rounded border-stone-300 text-sm">
                    <button
                        type="submit"
                        :disabled="roleForm.processing"
                        :aria-label="t('common.add')"
                        class="flex items-center justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </form>

                <div class="divide-y divide-stone-100">
                    <div v-for="r in roles" :key="r.id">
                        <button
                            type="button"
                            @click="toggleExpanded(r.id)"
                            class="flex w-full items-center justify-between py-3 text-left"
                        >
                            <span class="flex items-center gap-2">
                                <span class="font-medium text-stone-900">{{ r.name }}</span>
                                <span v-if="r.is_protected" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{{ t('roles.protected') }}</span>
                                <span class="text-xs text-stone-400">{{ r.permission_ids.length }} / {{ permissions.length }}</span>
                            </span>
                            <ChevronDownIcon class="size-4 text-stone-400 transition-transform" :class="{ 'rotate-180': expandedRoleId === r.id }" />
                        </button>

                        <div v-if="expandedRoleId === r.id" class="mb-3 rounded-md bg-stone-50 p-3">
                            <p v-if="r.is_protected" class="mb-2 text-xs text-amber-700">{{ t('roles.protected_note') }}</p>
                            <div class="grid grid-cols-1 gap-x-4 gap-y-1.5 sm:grid-cols-2">
                                <label
                                    v-for="p in permissions"
                                    :key="p.id"
                                    class="flex items-center gap-2 text-sm"
                                    :class="r.is_protected ? 'text-stone-400' : 'cursor-pointer text-stone-700'"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="r.permission_ids.includes(p.id)"
                                        :disabled="r.is_protected"
                                        @change="togglePermission(r, p)"
                                    >
                                    {{ p.description }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-if="roles.length === 0" class="text-sm text-stone-400">{{ t('roles.none_yet') }}</p>
            </section>
        </main>
    </AppLayout>
</template>
