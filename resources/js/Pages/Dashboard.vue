<script setup>
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-stone-100">
        <header class="flex items-center justify-between border-b border-stone-200 bg-white px-6 py-4">
            <h1 class="text-lg font-semibold text-stone-900">Ledger &amp; Loom</h1>
            <div class="flex items-center gap-4">
                <span class="text-sm text-stone-600">{{ page.props.auth.user.name }}</span>
                <button @click="logout" class="text-sm text-stone-500 hover:text-stone-900">Sign out</button>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-6 py-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
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
    </div>
</template>
