<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
});

const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

const unitForm = useForm({ name: '', abbreviation: '' });

function submitUnit() {
    unitForm.post('/products/units', { preserveScroll: true, onSuccess: () => unitForm.reset() });
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
</script>

<template>
    <AppLayout title="Products">
        <main class="mx-auto max-w-4xl space-y-6 p-6">
            <!-- Units -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">Units</h2>
                    <button type="button" @click="toggle('unit')" class="text-sm text-stone-600 underline">+ Add Unit</button>
                </div>

                <form v-if="activePanel === 'unit'" @submit.prevent="submitUnit" class="mb-3 grid grid-cols-3 gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="unitForm.name" type="text" placeholder="Name (e.g. Meter)" class="rounded border-stone-300 text-sm">
                    <input v-model="unitForm.abbreviation" type="text" placeholder="Abbreviation (e.g. m)" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="unitForm.processing" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Add</button>
                </form>

                <div class="flex flex-wrap gap-2">
                    <span v-for="u in units" :key="u.id" class="rounded-full bg-stone-100 px-3 py-1 text-xs text-stone-700">
                        {{ u.name }}<span v-if="u.abbreviation" class="text-stone-400"> ({{ u.abbreviation }})</span>
                    </span>
                    <span v-if="units.length === 0" class="text-sm text-stone-400">No units yet.</span>
                </div>
            </section>

            <!-- Products -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">Products</h2>
                    <button type="button" @click="toggle('product')" class="text-sm text-stone-600 underline">+ Add Product</button>
                </div>

                <form v-if="activePanel === 'product'" @submit.prevent="submitProduct" class="mb-4 grid grid-cols-4 gap-2 rounded-md border border-stone-200 p-3">
                    <input v-model="productForm.name" type="text" placeholder="Name" class="rounded border-stone-300 text-sm">
                    <input v-model="productForm.sku" type="text" placeholder="SKU (optional)" class="rounded border-stone-300 text-sm">
                    <select v-model="productForm.base_unit_id" class="rounded border-stone-300 text-sm">
                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <input v-model="productForm.low_stock_threshold" type="number" step="0.0001" placeholder="Low stock alert (optional)" class="rounded border-stone-300 text-sm">
                    <button type="submit" :disabled="productForm.processing" class="col-span-4 rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Add</button>
                </form>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase text-stone-500">
                            <th class="py-2">Name</th><th>SKU</th><th>Base unit</th><th>Alt. units</th><th>Status</th><th></th>
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
                                <td class="text-stone-500">{{ p.status }}</td>
                                <td class="text-right">
                                    <button type="button" @click="toggle(`unit-${p.id}`)" class="text-xs text-stone-600 underline">+ Alt. unit</button>
                                </td>
                            </tr>
                            <tr v-if="activePanel === `unit-${p.id}`" class="border-b border-stone-100 bg-stone-50">
                                <td colspan="6" class="p-2">
                                    <div class="flex items-center gap-2">
                                        <select v-model="conversionForm(p.id).unit_id" class="rounded border-stone-300 text-sm">
                                            <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                                        </select>
                                        <span class="text-xs text-stone-500">= how many {{ p.base_unit }}?</span>
                                        <input v-model="conversionForm(p.id).factor" type="number" step="0.0001" placeholder="Factor" class="w-28 rounded border-stone-300 text-sm">
                                        <button type="button" @click="submitConversion(p.id)" :disabled="conversionForm(p.id).processing" class="rounded-md bg-stone-900 px-3 py-1.5 text-xs text-white hover:bg-stone-700">Add</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p v-if="products.length === 0" class="text-sm text-stone-400">No products yet.</p>
            </section>
        </main>
    </AppLayout>
</template>
