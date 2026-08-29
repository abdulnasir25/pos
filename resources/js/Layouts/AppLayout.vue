<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: '' },
});

const page = usePage();

// permission: null means always visible to any authenticated user.
const navItems = [
    { label: 'Dashboard', href: '/dashboard', permission: null },
    { label: 'POS', href: '/pos', permission: 'sales.create' },
    { label: 'Products', href: '/products', permission: 'products.manage' },
    { label: 'Warehouses', href: '/warehouses', permission: 'warehouses.manage' },
    { label: 'Customers', href: '/customers', permission: 'customers.manage' },
    { label: 'Purchases', href: '/purchases', permission: 'purchases.manage' },
    { label: 'Partners', href: '/partners', permission: 'partners.manage' },
    { label: 'Expenses', href: '/expenses', permission: 'expenses.manage' },
    { label: 'Employees', href: '/employees', permission: 'employees.view' },
    { label: 'Commission', href: '/commission', permission: 'commission.manage' },
    { label: 'Cash Register', href: '/cash-register', permission: 'cash_register.manage' },
    { label: 'Financial Periods', href: '/financial-periods', permission: 'financial_periods.manage' },
    { label: 'Accounting', href: '/accounting', permission: 'accounting.view' },
    { label: 'Reports', href: '/reports', permission: 'reports.view' },
    { label: 'Audit Log', href: '/audit-log', permission: 'audit_logs.view' },
];

const visibleNavItems = computed(() => {
    const permissions = page.props.auth.user?.permissions ?? [];

    return navItems.filter((item) => item.permission === null || permissions.includes(item.permission));
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
        <aside class="flex w-56 flex-shrink-0 flex-col border-r border-stone-200 bg-white">
            <div class="border-b border-stone-200 px-4 py-4">
                <span class="text-base font-semibold text-stone-900">Ledger &amp; Loom</span>
            </div>
            <nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto p-2">
                <Link
                    v-for="item in visibleNavItems"
                    :key="item.href"
                    :href="item.href"
                    class="rounded-md px-3 py-2 text-sm"
                    :class="isActive(item.href) ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-100'"
                >
                    {{ item.label }}
                </Link>
            </nav>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="flex items-center justify-between border-b border-stone-200 bg-white px-6 py-4">
                <h1 class="text-lg font-semibold text-stone-900">{{ title }}</h1>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-stone-500">{{ page.props.auth.user?.name }}</span>
                    <button type="button" @click="logout" class="text-sm text-stone-500 hover:text-stone-900">Sign out</button>
                </div>
            </header>

            <div v-if="page.props.flash?.success" class="mx-6 mt-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ page.props.flash.success }}
            </div>
            <div v-if="Object.keys(page.props.errors ?? {}).length" class="mx-6 mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p v-for="(msg, key) in page.props.errors" :key="key">{{ msg }}</p>
            </div>

            <slot />
        </div>
    </div>
</template>
