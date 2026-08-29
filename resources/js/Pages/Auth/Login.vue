<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-stone-100 px-4">
        <div class="w-full max-w-sm rounded-lg bg-white p-8 shadow">
            <h1 class="mb-6 text-xl font-semibold text-stone-900">Sign in</h1>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-stone-700">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autofocus
                        autocomplete="username"
                        class="mt-1 block w-full rounded-md border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500"
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
                        class="mt-1 block w-full rounded-md border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500"
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
                    class="w-full rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 disabled:opacity-50"
                >
                    Sign in
                </button>
            </form>
        </div>
    </div>
</template>
