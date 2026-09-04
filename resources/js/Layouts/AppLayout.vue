<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from '../i18n';

defineProps({
    title: { type: String, default: '' },
});

const page = usePage();
const { t, locale, toggleLocale } = useI18n();

// permission: null means always visible to any authenticated user.
const navItems = computed(() => [
    { label: t('nav.home'), href: '/dashboard', permission: null },
    { label: t('nav.new_sale'), href: '/pos', permission: 'sales.create' },
    { label: t('nav.products'), href: '/products', permission: 'products.manage' },
    { label: t('nav.warehouses'), href: '/warehouses', permission: 'warehouses.manage' },
    { label: t('nav.customers'), href: '/customers', permission: 'customers.manage' },
    { label: t('nav.purchases'), href: '/purchases', permission: 'purchases.manage' },
    { label: t('nav.partners'), href: '/partners', permission: 'partners.manage' },
    { label: t('nav.expenses'), href: '/expenses', permission: 'expenses.manage' },
    { label: t('nav.employees'), href: '/employees', permission: 'employees.view' },
    { label: t('nav.commission'), href: '/commission', permission: 'commission.manage' },
    { label: t('nav.cash_register'), href: '/cash-register', permission: 'cash_register.manage' },
    { label: t('nav.financial_periods'), href: '/financial-periods', permission: 'financial_periods.manage' },
    { label: t('nav.accounting'), href: '/accounting', permission: 'accounting.view' },
    { label: t('nav.reports'), href: '/reports', permission: 'reports.view' },
    { label: t('nav.audit_log'), href: '/audit-log', permission: 'audit_logs.view' },
    { label: t('nav.my_access'), href: '/access', permission: null },
]);

const visibleNavItems = computed(() => {
    const permissions = page.props.auth.user?.permissions ?? [];

    return navItems.value.filter((item) => item.permission === null || permissions.includes(item.permission));
});

function isActive(href) {
    return page.url === href || page.url.startsWith(`${href}/`);
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="flex min-h-screen bg-stone-100">
        <aside class="flex w-64 flex-shrink-0 flex-col bg-gradient-to-b from-indigo-950 to-stone-900">
            <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
                <span class="flex size-9 flex-shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-400 to-indigo-600 text-sm font-semibold text-white shadow-sm">L&amp;L</span>
                <span class="text-lg font-semibold leading-tight text-white">{{ t('nav.brand') }}</span>
            </div>
            <nav class="flex flex-1 flex-col gap-1 overflow-y-auto p-3">
                <Link
                    v-for="item in visibleNavItems"
                    :key="item.href"
                    :href="item.href"
                    class="rounded-lg px-3.5 py-2.5 text-[0.95rem] font-medium"
                    :class="isActive(item.href) ? 'bg-white text-indigo-700 shadow-sm' : 'bg-white/5 text-indigo-100/90 hover:bg-white/15 hover:text-white'"
                >
                    {{ item.label }}
                </Link>
            </nav>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="flex items-center justify-between border-b border-stone-200 bg-white px-8 py-5">
                <div class="flex items-center gap-3">
                    <span class="h-6 w-1 rounded-full bg-indigo-600"></span>
                    <h1 class="text-2xl font-semibold text-stone-900">{{ title }}</h1>
                </div>
                <div class="flex items-center gap-4">
                    <button
                        type="button"
                        @click="toggleLocale"
                        class="rounded-lg border border-indigo-200 px-3 py-1.5 text-sm font-medium text-indigo-700 hover:bg-indigo-50"
                    >
                        {{ t('lang.switch') }}
                    </button>
                    <span class="text-[0.95rem] text-stone-600">{{ page.props.auth.user?.name }}</span>
                    <button
                        type="button"
                        @click="logout"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium text-stone-500 hover:bg-stone-100 hover:text-stone-900"
                    >
                        {{ t('nav.sign_out') }}
                    </button>
                </div>
            </header>

            <div v-if="page.props.flash?.success" class="mx-8 mt-5 rounded-lg border border-emerald-300 bg-emerald-50 px-5 py-3.5 text-[0.95rem] text-emerald-800">
                {{ page.props.flash.success }}
            </div>
            <div v-if="Object.keys(page.props.errors ?? {}).length" class="mx-8 mt-5 rounded-lg border border-red-300 bg-red-50 px-5 py-3.5 text-[0.95rem] text-red-800">
                <p v-for="(msg, key) in page.props.errors" :key="key">{{ msg }}</p>
            </div>

            <slot />
        </div>
    </div>
</template>
