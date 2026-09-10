<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from '../i18n';
import MenuIcon from '../Components/icons/MenuIcon.vue';
import CloseIcon from '../Components/icons/CloseIcon.vue';
import ChevronDownIcon from '../Components/icons/ChevronDownIcon.vue';

defineProps({
    title: { type: String, default: '' },
});

const page = usePage();
const { t, locale, toggleLocale } = useI18n();

const sidebarOpen = ref(false);

watch(() => page.url, () => { sidebarOpen.value = false; });

function isActive(href) {
    return page.url === href || page.url.startsWith(`${href}/`);
}

// permission: null means always visible to any authenticated user.
// A handful of frequent, single-purpose links stay flat; everything
// else that's more configuration than daily work is grouped under a
// collapsible section, the same disclosure pattern already used on
// the Commission/Roles pages — this is what keeps the sidebar from
// growing one row per module forever.
const navStructure = computed(() => [
    { type: 'link', label: t('nav.home'), href: '/dashboard', permission: null },
    { type: 'link', label: t('nav.new_sale'), href: '/pos', permission: 'sales.create' },
    { type: 'link', label: t('nav.sales'), href: '/sales', permission: 'sales.view' },
    { type: 'link', label: t('nav.customers'), href: '/customers', permission: 'customers.manage' },
    { type: 'link', label: t('nav.purchases'), href: '/purchases', permission: 'purchases.manage' },
    {
        type: 'group',
        key: 'inventory',
        label: t('nav.group_inventory'),
        items: [
            { label: t('nav.products'), href: '/products', permission: 'products.manage' },
            { label: t('nav.warehouses'), href: '/warehouses', permission: 'warehouses.manage' },
            { label: t('nav.inventory'), href: '/inventory', permission: 'inventory.view' },
        ],
    },
    {
        type: 'group',
        key: 'people',
        label: t('nav.group_people'),
        items: [
            { label: t('nav.partners'), href: '/partners', permission: 'partners.manage' },
            { label: t('nav.employees'), href: '/employees', permission: 'employees.view' },
            { label: t('nav.commission'), href: '/commission', permission: 'commission.manage' },
        ],
    },
    {
        type: 'group',
        key: 'finance',
        label: t('nav.group_finance'),
        items: [
            { label: t('nav.expenses'), href: '/expenses', permission: 'expenses.manage' },
            { label: t('nav.cash_register'), href: '/cash-register', permission: 'cash_register.manage' },
            { label: t('nav.financial_periods'), href: '/financial-periods', permission: 'financial_periods.manage' },
            { label: t('nav.accounting'), href: '/accounting', permission: 'accounting.view' },
            { label: t('nav.reports'), href: '/reports', permission: 'reports.view' },
        ],
    },
    {
        type: 'group',
        key: 'admin',
        label: t('nav.group_admin'),
        items: [
            { label: t('nav.audit_log'), href: '/audit-log', permission: 'audit_logs.view' },
            { label: t('nav.users'), href: '/access/users', permission: 'roles.manage' },
            { label: t('nav.roles'), href: '/access/roles', permission: 'roles.manage' },
            { label: t('nav.settings'), href: '/settings', permission: 'settings.manage' },
            { label: t('nav.my_access'), href: '/access', permission: null },
        ],
    },
]);

function itemVisible(item, permissions) {
    return item.permission === null || permissions.includes(item.permission);
}

const visibleNavStructure = computed(() => {
    const permissions = page.props.auth.user?.permissions ?? [];

    return navStructure.value
        .map((entry) => entry.type === 'link'
            ? entry
            : { ...entry, items: entry.items.filter((item) => itemVisible(item, permissions)) })
        .filter((entry) => entry.type === 'link' ? itemVisible(entry, permissions) : entry.items.length > 0);
});

// A group starts open if the page you're currently on lives inside
// it, so landing on e.g. /reports doesn't hide the very link you just
// followed behind a collapsed section.
const openGroups = reactive({});

watch(visibleNavStructure, (structure) => {
    for (const entry of structure) {
        if (entry.type === 'group' && !(entry.key in openGroups)) {
            openGroups[entry.key] = entry.items.some((item) => isActive(item.href));
        }
    }
}, { immediate: true });

function toggleGroup(key) {
    openGroups[key] = !openGroups[key];
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="flex min-h-screen bg-stone-100">
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-stone-900/50 lg:hidden"
            @click="sidebarOpen = false"
        ></div>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-shrink-0 -translate-x-full flex-col bg-gradient-to-b from-indigo-950 to-stone-900 transition-transform duration-200 lg:static lg:translate-x-0"
            :class="{ 'translate-x-0': sidebarOpen }"
        >
            <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
                <span class="flex size-9 flex-shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-400 to-indigo-600 text-sm font-semibold text-white shadow-sm">L&amp;L</span>
                <span class="text-lg font-semibold leading-tight text-white">{{ t('nav.brand') }}</span>
                <button
                    type="button"
                    @click="sidebarOpen = false"
                    :aria-label="t('common.close')"
                    class="ml-auto flex size-8 items-center justify-center rounded-lg text-indigo-200 hover:bg-white/10 lg:hidden"
                >
                    <CloseIcon class="size-5" />
                </button>
            </div>
            <nav class="flex flex-1 flex-col gap-1 overflow-y-auto p-3">
                <template v-for="entry in visibleNavStructure" :key="entry.key ?? entry.href">
                    <Link
                        v-if="entry.type === 'link'"
                        :href="entry.href"
                        class="rounded-lg px-3.5 py-2.5 text-[0.95rem] font-medium"
                        :class="isActive(entry.href) ? 'bg-white text-indigo-700 shadow-sm' : 'bg-white/5 text-indigo-100/90 hover:bg-white/15 hover:text-white'"
                    >
                        {{ entry.label }}
                    </Link>

                    <div v-else>
                        <button
                            type="button"
                            @click="toggleGroup(entry.key)"
                            class="flex w-full items-center justify-between rounded-lg px-3.5 py-2.5 text-[0.95rem] font-medium text-indigo-100/90 hover:bg-white/15 hover:text-white"
                        >
                            {{ entry.label }}
                            <ChevronDownIcon class="size-4 flex-shrink-0 transition-transform" :class="{ 'rotate-180': openGroups[entry.key] }" />
                        </button>
                        <div v-if="openGroups[entry.key]" class="mt-1 flex flex-col gap-1 pl-3">
                            <Link
                                v-for="item in entry.items"
                                :key="item.href"
                                :href="item.href"
                                class="rounded-lg px-3.5 py-2 text-sm font-medium"
                                :class="isActive(item.href) ? 'bg-white text-indigo-700 shadow-sm' : 'bg-white/5 text-indigo-100/80 hover:bg-white/15 hover:text-white'"
                            >
                                {{ item.label }}
                            </Link>
                        </div>
                    </div>
                </template>
            </nav>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="flex items-center justify-between gap-3 border-b border-stone-200 bg-white px-4 py-4 sm:px-8 sm:py-5">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        @click="sidebarOpen = true"
                        :aria-label="t('common.menu')"
                        class="flex size-9 flex-shrink-0 items-center justify-center rounded-lg text-stone-600 hover:bg-stone-100 lg:hidden"
                    >
                        <MenuIcon class="size-5" />
                    </button>
                    <span class="hidden h-6 w-1 flex-shrink-0 rounded-full bg-indigo-600 sm:block"></span>
                    <h1 class="truncate text-lg font-semibold text-stone-900 sm:text-2xl">{{ title }}</h1>
                </div>
                <div class="flex flex-shrink-0 items-center gap-2 sm:gap-4">
                    <button
                        type="button"
                        @click="toggleLocale"
                        class="rounded-lg border border-indigo-200 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50 sm:px-3 sm:text-sm"
                    >
                        {{ t('lang.switch') }}
                    </button>
                    <span class="hidden text-[0.95rem] text-stone-600 md:inline">{{ page.props.auth.user?.name }}</span>
                    <button
                        type="button"
                        @click="logout"
                        class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-stone-500 hover:bg-stone-100 hover:text-stone-900 sm:px-3 sm:text-sm"
                    >
                        {{ t('nav.sign_out') }}
                    </button>
                </div>
            </header>

            <div v-if="page.props.flash?.success" class="mx-4 mt-5 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3.5 text-[0.95rem] text-emerald-800 sm:mx-8 sm:px-5">
                {{ page.props.flash.success }}
            </div>
            <div v-if="Object.keys(page.props.errors ?? {}).length" class="mx-4 mt-5 rounded-lg border border-red-300 bg-red-50 px-4 py-3.5 text-[0.95rem] text-red-800 sm:mx-8 sm:px-5">
                <p v-for="(msg, key) in page.props.errors" :key="key">{{ msg }}</p>
            </div>

            <slot />
        </div>
    </div>
</template>
