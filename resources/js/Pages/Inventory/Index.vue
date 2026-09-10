<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useI18n } from '../../i18n';

const props = defineProps({
    products: { type: Array, default: () => [] },
    warehouses: { type: Array, default: () => [] },
    movements: { type: Array, default: () => [] },
});

const { t } = useI18n();
const page = usePage();
const canAdjust = computed(() => (page.props.auth.user?.permissions ?? []).includes('inventory.adjust'));

const selectedProduct = computed(() => props.products.find((p) => p.id === form.product_id));
const availableUnits = computed(() => selectedProduct.value?.units ?? []);

const form = useForm({
    product_id: props.products[0]?.id ?? null,
    warehouse_id: props.warehouses[0]?.id ?? null,
    unit_id: props.products[0]?.units[0]?.id ?? null,
    reason: 'found',
    quantity: '',
});

watch(() => form.product_id, () => {
    form.unit_id = availableUnits.value[0]?.id ?? null;
});

function submitAdjustment() {
    form.post('/inventory/adjustments', {
        preserveScroll: true,
        onSuccess: () => form.reset('quantity'),
    });
}

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(4);
}

function reasonLabel(reason) {
    return t(`inventory.reason_${reason}`);
}
</script>

<template>
    <AppLayout :title="t('inventory.title')">
        <main class="mx-auto max-w-4xl space-y-6 p-6">
            <section v-if="canAdjust" class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">{{ t('inventory.form_title') }}</h2>

                <form @submit.prevent="submitAdjustment" class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <select v-model="form.product_id" class="rounded border-stone-300 text-sm">
                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <select v-model="form.warehouse_id" class="rounded border-stone-300 text-sm">
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                    <select v-model="form.unit_id" class="rounded border-stone-300 text-sm">
                        <option v-for="u in availableUnits" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <select v-model="form.reason" class="rounded border-stone-300 text-sm sm:col-span-2">
                        <option value="found">{{ t('inventory.reason_option_found') }}</option>
                        <option value="shrinkage">{{ t('inventory.reason_option_shrinkage') }}</option>
                        <option value="damage">{{ t('inventory.reason_option_damage') }}</option>
                    </select>
                    <input v-model="form.quantity" type="number" step="0.0001" min="0.0001" :placeholder="t('inventory.quantity_placeholder')" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700 sm:col-span-3">
                        {{ t('inventory.record') }}
                    </button>
                </form>
                <p v-if="selectedProduct" class="mt-3 text-xs text-stone-500" dir="auto">
                    {{ selectedProduct.name }}: {{ money(selectedProduct.stock_by_warehouse[form.warehouse_id]) }}
                </p>
            </section>

            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">{{ t('inventory.movements_title') }}</h2>
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">{{ t('inventory.col_date') }}</th>
                            <th>{{ t('inventory.col_product') }}</th>
                            <th>{{ t('inventory.col_warehouse') }}</th>
                            <th>{{ t('inventory.col_reason') }}</th>
                            <th class="text-right">{{ t('inventory.col_quantity') }}</th>
                            <th>{{ t('inventory.col_by') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in movements" :key="m.id" class="border-b border-stone-100">
                            <td class="py-2 text-stone-500">{{ m.created_at }}</td>
                            <td class="text-stone-900">{{ m.product }}</td>
                            <td class="text-stone-500">{{ m.warehouse }}</td>
                            <td class="text-stone-500">{{ reasonLabel(m.reason) }}</td>
                            <td class="text-right tabular-nums" :class="parseFloat(m.quantity) < 0 ? 'text-red-600' : 'text-emerald-700'">
                                {{ parseFloat(m.quantity) > 0 ? '+' : '' }}{{ m.quantity }}
                            </td>
                            <td class="text-stone-500">{{ m.created_by ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <p v-if="movements.length === 0" class="text-sm text-stone-400">{{ t('inventory.movements_none_yet') }}</p>
            </section>
        </main>
    </AppLayout>
</template>
