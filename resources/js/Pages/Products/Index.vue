<script setup>
import { ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PlusIcon from '../../Components/icons/PlusIcon.vue';
import PencilIcon from '../../Components/icons/PencilIcon.vue';
import TrashIcon from '../../Components/icons/TrashIcon.vue';
import CheckIcon from '../../Components/icons/CheckIcon.vue';
import { useI18n } from '../../i18n';

const props = defineProps({
    products: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
});

const { t } = useI18n();
const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

const unitForm = useForm({ name: '', abbreviation: '' });

function submitUnit() {
    unitForm.post('/products/units', { preserveScroll: true, onSuccess: () => unitForm.reset() });
}

const unitEditForms = ref({});

function unitEditForm(unit) {
    if (!unitEditForms.value[unit.id]) {
        unitEditForms.value[unit.id] = useForm({ name: unit.name, abbreviation: unit.abbreviation });
    }
    return unitEditForms.value[unit.id];
}

function submitUnitEdit(unit) {
    unitEditForm(unit).post(`/products/units/${unit.id}`, {
        preserveScroll: true,
        onSuccess: () => toggle(null),
    });
}

const productForm = useForm({ base_unit_id: props.units[0]?.id ?? null, name: '', sku: '', low_stock_threshold: '' });

function submitProduct() {
    productForm.post('/products', { preserveScroll: true, onSuccess: () => productForm.reset('name', 'sku', 'low_stock_threshold') });
}

watch(() => props.units, (list) => {
    if (productForm.base_unit_id === null && list.length > 0) productForm.base_unit_id = list[0].id;
});

const conversionForms = ref({});

function conversionForm(productId) {
    if (!conversionForms.value[productId]) {
        conversionForms.value[productId] = useForm({ unit_id: props.units[0]?.id ?? null, factor: '' });
    }
    return conversionForms.value[productId];
}

function submitConversion(productId) {
    conversionForm(productId).post(`/products/${productId}/conversions`, {
        preserveScroll: true,
        onSuccess: () => { conversionForm(productId).reset('factor'); toggle(null); },
    });
}

const productEditForms = ref({});

function productEditForm(product) {
    if (!productEditForms.value[product.id]) {
        productEditForms.value[product.id] = useForm({
            name: product.name,
            sku: product.sku,
            low_stock_threshold: product.low_stock_threshold,
        });
    }
    return productEditForms.value[product.id];
}

function submitProductEdit(product) {
    productEditForm(product).post(`/products/${product.id}`, {
        preserveScroll: true,
        onSuccess: () => toggle(null),
    });
}

function toggleProductStatus(product) {
    router.post(`/products/${product.id}/toggle-status`, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="t('products.title')">
        <main class="mx-auto max-w-4xl space-y-6 p-6">
            <!-- Units -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">{{ t('products.units_title') }}</h2>
                    <button
                        type="button"
                        @click="toggle('unit')"
                        :aria-label="t('common.add')"
                        class="flex size-8 items-center justify-center rounded-full bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </div>

                <form v-if="activePanel === 'unit'" @submit.prevent="submitUnit" class="mb-3 grid grid-cols-3 gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="unitForm.name" type="text" :placeholder="t('products.unit_name_placeholder')" class="rounded border-stone-300 text-sm">
                    <input v-model="unitForm.abbreviation" type="text" :placeholder="t('products.unit_short_placeholder')" class="rounded border-stone-300 text-sm">
                    <button
                        type="submit"
                        :disabled="unitForm.processing"
                        :aria-label="t('common.add')"
                        class="flex items-center justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </form>

                <div class="flex flex-wrap gap-2">
                    <span v-for="u in units" :key="u.id" class="inline-flex items-center gap-1 rounded-full bg-stone-100 py-1 pl-3 pr-1.5 text-xs text-stone-700">
                        {{ u.name }}<span v-if="u.abbreviation" class="text-stone-400"> ({{ u.abbreviation }})</span>
                        <button type="button" @click="toggle(`unit-edit-${u.id}`)" :aria-label="t('common.edit')" class="ml-1 inline-flex size-5 items-center justify-center rounded-full text-indigo-600 hover:bg-indigo-100">
                            <PencilIcon class="size-3" />
                        </button>
                    </span>
                    <span v-if="units.length === 0" class="text-sm text-stone-400">{{ t('products.none_yet') }}</span>
                </div>

                <div v-for="u in units" :key="`edit-row-${u.id}`">
                    <div v-if="activePanel === `unit-edit-${u.id}`" class="mt-3 flex items-center gap-2 rounded-md border border-stone-200 p-3">
                        <input v-model="unitEditForm(u).name" type="text" class="rounded border-stone-300 text-sm">
                        <input v-model="unitEditForm(u).abbreviation" type="text" class="rounded border-stone-300 text-sm">
                        <button type="button" @click="submitUnitEdit(u)" :disabled="unitEditForm(u).processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs text-white hover:bg-indigo-700">{{ t('common.save') }}</button>
                    </div>
                </div>
            </section>

            <!-- Products -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">{{ t('products.list_title') }}</h2>
                    <button
                        type="button"
                        @click="toggle('product')"
                        :aria-label="t('common.add')"
                        class="flex size-8 items-center justify-center rounded-full bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </div>

                <form v-if="activePanel === 'product'" @submit.prevent="submitProduct" class="mb-4 grid grid-cols-4 gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="productForm.name" type="text" :placeholder="t('products.name_placeholder')" class="rounded border-stone-300 text-sm">
                    <input v-model="productForm.sku" type="text" :placeholder="t('products.sku_placeholder')" class="rounded border-stone-300 text-sm">
                    <select v-model="productForm.base_unit_id" class="rounded border-stone-300 text-sm">
                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <input v-model="productForm.low_stock_threshold" type="number" step="0.0001" :placeholder="t('products.low_stock_placeholder')" class="rounded border-stone-300 text-sm">
                    <button
                        type="submit"
                        :disabled="productForm.processing"
                        :aria-label="t('common.add')"
                        class="col-span-4 flex items-center justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-4" />
                    </button>
                </form>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">{{ t('common.name') }}</th><th>{{ t('products.sku') }}</th><th>{{ t('products.base_unit') }}</th><th>{{ t('products.alt_units') }}</th><th>{{ t('common.status') }}</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="p in products" :key="p.id">
                            <tr class="border-b border-stone-100">
                                <td class="py-2 text-stone-900">{{ p.name }}</td>
                                <td class="text-stone-500">{{ p.sku ?? '—' }}</td>
                                <td class="text-stone-500">{{ p.base_unit }}</td>
                                <td class="text-stone-500">
                                    <span v-if="p.conversions.length === 0">—</span>
                                    <span v-else>{{ p.conversions.map(c => `${c.unit} (×${c.factor})`).join(', ') }}</span>
                                </td>
                                <td class="text-stone-500">{{ p.status === 'active' ? t('common.active') : t('common.inactive') }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <button
                                        type="button"
                                        @click="toggle(`unit-${p.id}`)"
                                        :aria-label="t('products.add_alt_unit')"
                                        class="mr-1 inline-flex size-7 items-center justify-center rounded-full text-indigo-600 hover:bg-indigo-50"
                                    >
                                        <PlusIcon class="size-3.5" />
                                    </button>
                                    <button type="button" @click="toggle(`edit-${p.id}`)" :aria-label="t('common.edit')" class="mr-1 inline-flex size-7 items-center justify-center rounded-full text-indigo-600 hover:bg-indigo-50">
                                        <PencilIcon class="size-3.5" />
                                    </button>
                                    <button
                                        v-if="p.status === 'active'"
                                        type="button"
                                        @click="toggleProductStatus(p)"
                                        :aria-label="t('common.deactivate')"
                                        class="inline-flex size-7 items-center justify-center rounded-full text-red-600 hover:bg-red-50"
                                    >
                                        <TrashIcon class="size-3.5" />
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        @click="toggleProductStatus(p)"
                                        :aria-label="t('common.activate')"
                                        class="inline-flex size-7 items-center justify-center rounded-full text-emerald-600 hover:bg-emerald-50"
                                    >
                                        <CheckIcon class="size-3.5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="activePanel === `unit-${p.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="6" class="p-2">
                                    <div class="flex items-center gap-2">
                                        <select v-model="conversionForm(p.id).unit_id" class="rounded border-stone-300 text-sm">
                                            <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                                        </select>
                                        <span class="text-xs text-stone-500">{{ t('products.alt_unit_hint', { unit: p.base_unit }) }}</span>
                                        <input v-model="conversionForm(p.id).factor" type="number" step="0.0001" :placeholder="t('products.factor_placeholder')" class="w-28 rounded border-stone-300 text-sm">
                                        <button
                                            type="button"
                                            @click="submitConversion(p.id)"
                                            :disabled="conversionForm(p.id).processing"
                                            :aria-label="t('common.add')"
                                            class="flex size-8 items-center justify-center rounded-md bg-indigo-600 text-white hover:bg-indigo-700"
                                        >
                                            <PlusIcon class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="activePanel === `edit-${p.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="6" class="p-2">
                                    <div class="grid grid-cols-4 gap-2">
                                        <input v-model="productEditForm(p).name" type="text" :placeholder="t('products.name_placeholder')" class="rounded border-stone-300 text-sm">
                                        <input v-model="productEditForm(p).sku" type="text" :placeholder="t('products.sku_placeholder')" class="rounded border-stone-300 text-sm">
                                        <input v-model="productEditForm(p).low_stock_threshold" type="number" step="0.0001" :placeholder="t('products.low_stock_placeholder')" class="rounded border-stone-300 text-sm">
                                        <button type="button" @click="submitProductEdit(p)" :disabled="productEditForm(p).processing" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">{{ t('common.save') }}</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p v-if="products.length === 0" class="text-sm text-stone-400">{{ t('products.list_empty') }}</p>
            </section>
        </main>
    </AppLayout>
</template>
