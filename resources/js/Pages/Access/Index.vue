<script setup>
import { usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const page = usePage();
</script>

<template>
    <AppLayout title="My Access">
        <main class="mx-auto max-w-2xl px-6 py-8">
            <div class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-base font-medium text-stone-900">Your access</h2>

                <p class="mb-2 text-sm text-stone-600">Roles</p>
                <div class="mb-4 flex flex-wrap gap-2">
                    <span
                        v-for="role in page.props.auth.user.roles"
                        :key="role"
                        class="rounded-full bg-stone-900 px-3 py-1 text-xs font-medium text-white"
                    >
                        {{ role }}
                    </span>
                    <span v-if="page.props.auth.user.roles.length === 0" class="text-sm text-stone-400">No roles assigned</span>
                </div>

                <p class="mb-2 text-sm text-stone-600">Permissions</p>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="permission in page.props.auth.user.permissions"
                        :key="permission"
                        class="rounded-full border border-stone-300 px-3 py-1 text-xs text-stone-700"
                    >
                        {{ permission }}
                    </span>
                    <span v-if="page.props.auth.user.permissions.length === 0" class="text-sm text-stone-400">No permissions granted</span>
                </div>
            </div>
        </main>
    </AppLayout>
</template>
