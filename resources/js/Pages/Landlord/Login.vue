<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/landlord/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-stone-100 via-stone-100 to-indigo-50 px-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-xl shadow-stone-200/60">
            <span class="mb-4 flex size-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-sm font-semibold text-white shadow-sm">L&amp;L</span>
            <h1 class="mb-1 text-xl font-semibold text-stone-900">Platform admin</h1>
            <p class="mb-6 text-sm text-stone-500">SaaS billing &amp; tenant management</p>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-stone-700">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autofocus
                        autocomplete="username"
                        class="mt-1 block w-full rounded-md border-stone-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-stone-700">Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        class="mt-1 block w-full rounded-md border-stone-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-stone-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-stone-300">
                    Remember me
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    Sign in
                </button>
            </form>

            <div class="mt-6 rounded-lg border border-dashed border-indigo-200 bg-indigo-50/60 px-4 py-3 text-xs text-stone-600">
                <p class="mb-1 font-medium text-indigo-800">Demo credentials (remove before launch)</p>
                <p>Email: <span class="font-mono">owner@ledgerloom.test</span></p>
                <p>Password: <span class="font-mono">password123</span></p>
            </div>
        </div>
    </div>
</template>
