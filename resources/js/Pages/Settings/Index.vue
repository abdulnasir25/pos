<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useI18n } from '../../i18n';

const props = defineProps({
    settings: { type: Object, required: true },
});

const { t } = useI18n();

const form = useForm({
    shop_name: props.settings.shop_name,
    address: props.settings.address,
    phone: props.settings.phone,
    currency_symbol: props.settings.currency_symbol,
    receipt_footer: props.settings.receipt_footer,
});

function submit() {
    form.post('/settings', { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="t('settings.title')">
        <main class="mx-auto max-w-2xl space-y-6 p-6">
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-base font-medium text-stone-900">{{ t('settings.shop_details') }}</h2>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-stone-700">{{ t('settings.shop_name_label') }}</label>
                        <input v-model="form.shop_name" type="text" class="w-full rounded border-stone-300 text-sm" dir="auto">
                        <p class="mt-1 text-xs text-stone-400">{{ t('settings.shop_name_hint') }}</p>
                        <p v-if="form.errors.shop_name" class="mt-1 text-xs text-red-600">{{ form.errors.shop_name }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-stone-700">{{ t('settings.address_label') }}</label>
                        <input v-model="form.address" type="text" class="w-full rounded border-stone-300 text-sm" dir="auto">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-stone-700">{{ t('settings.phone_label') }}</label>
                        <input v-model="form.phone" type="text" class="w-full rounded border-stone-300 text-sm">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-stone-700">{{ t('settings.currency_symbol_label') }}</label>
                        <input v-model="form.currency_symbol" type="text" class="w-32 rounded border-stone-300 text-sm">
                        <p class="mt-1 text-xs text-stone-400">{{ t('settings.currency_symbol_hint') }}</p>
                        <p v-if="form.errors.currency_symbol" class="mt-1 text-xs text-red-600">{{ form.errors.currency_symbol }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-stone-700">{{ t('settings.receipt_footer_label') }}</label>
                        <textarea v-model="form.receipt_footer" rows="3" class="w-full rounded border-stone-300 text-sm" dir="auto"></textarea>
                        <p class="mt-1 text-xs text-stone-400">{{ t('settings.receipt_footer_hint') }}</p>
                    </div>

                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">
                        {{ t('settings.save') }}
                    </button>
                </form>
            </section>
        </main>
    </AppLayout>
</template>
