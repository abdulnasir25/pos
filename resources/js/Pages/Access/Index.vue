<script setup>
import { usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useI18n } from '../../i18n';

const page = usePage();
const { t } = useI18n();
</script>

<template>
    <AppLayout :title="t('access.title')">
        <main class="mx-auto max-w-2xl px-6 py-8">
            <div class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-base font-medium text-stone-900">{{ t('access.heading') }}</h2>

                <p class="mb-2 text-sm text-stone-600">{{ t('access.roles') }}</p>
                <div class="mb-4 flex flex-wrap gap-2">
                    <span
                        v-for="role in page.props.auth.user.roles"
                        :key="role"
                        class="rounded-full bg-stone-900 px-3 py-1 text-xs font-medium text-white"
                    >
                        {{ role }}
                    </span>
                    <span v-if="page.props.auth.user.roles.length === 0" class="text-sm text-stone-400">{{ t('access.no_roles') }}</span>
                </div>

                <p class="mb-2 text-sm text-stone-600">{{ t('access.permissions') }}</p>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="permission in page.props.auth.user.permissions"
                        :key="permission"
                        class="rounded-full border border-stone-300 px-3 py-1 text-xs text-stone-700"
                    >
                        {{ permission }}
                    </span>
                    <span v-if="page.props.auth.user.permissions.length === 0" class="text-sm text-stone-400">{{ t('access.no_permissions') }}</span>
                </div>
            </div>
        </main>
    </AppLayout>
</template>
