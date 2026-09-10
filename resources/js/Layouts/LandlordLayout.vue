<script setup>
import { ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import MenuIcon from '../Components/icons/MenuIcon.vue';
import CloseIcon from '../Components/icons/CloseIcon.vue';

defineProps({
    title: { type: String, default: '' },
});

const page = usePage();
const sidebarOpen = ref(false);

watch(() => page.url, () => { sidebarOpen.value = false; });

const navItems = [
    { label: 'Tenants', href: '/landlord/tenants' },
    { label: 'Plans', href: '/landlord/billing/plans' },
    { label: 'Subscriptions', href: '/landlord/billing/subscriptions' },
    { label: 'Invoices', href: '/landlord/billing/invoices' },
];

function isActive(href) {
    return page.url === href || page.url.startsWith(`${href}/`);
}

function logout() {
    router.post('/landlord/logout');
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
                <span class="text-lg font-semibold leading-tight text-white">Platform Admin</span>
                <button
                    type="button"
                    @click="sidebarOpen = false"
                    aria-label="Close"
                    class="ml-auto flex size-8 items-center justify-center rounded-lg text-indigo-200 hover:bg-white/10 lg:hidden"
                >
                    <CloseIcon class="size-5" />
                </button>
            </div>
            <nav class="flex flex-1 flex-col gap-1 overflow-y-auto p-3">
                <Link
                    v-for="item in navItems"
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
            <header class="flex items-center justify-between gap-3 border-b border-stone-200 bg-white px-4 py-4 sm:px-8 sm:py-5">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        @click="sidebarOpen = true"
                        aria-label="Menu"
                        class="flex size-9 flex-shrink-0 items-center justify-center rounded-lg text-stone-600 hover:bg-stone-100 lg:hidden"
                    >
                        <MenuIcon class="size-5" />
                    </button>
                    <span class="hidden h-6 w-1 flex-shrink-0 rounded-full bg-indigo-600 sm:block"></span>
                    <h1 class="truncate text-lg font-semibold text-stone-900 sm:text-2xl">{{ title }}</h1>
                </div>
                <div class="flex flex-shrink-0 items-center gap-2 sm:gap-4">
                    <span class="hidden text-[0.95rem] text-stone-600 md:inline">{{ page.props.auth.user?.name }}</span>
                    <button
                        type="button"
                        @click="logout"
                        class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-stone-500 hover:bg-stone-100 hover:text-stone-900 sm:px-3 sm:text-sm"
                    >
                        Sign out
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
