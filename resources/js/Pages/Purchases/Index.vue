<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PlusIcon from '../../Components/icons/PlusIcon.vue';
import TrashIcon from '../../Components/icons/TrashIcon.vue';
import { useI18n } from '../../i18n';

const props = defineProps({
    products: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
    warehouses: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    purchases: { type: Array, default: () => [] },
});

const { t } = useI18n();
const search = ref('');
const activePanel = ref(null);

function toggle(panel) {
    activePanel.value = activePanel.value === panel ? null : panel;
}

const filteredProducts = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (q === '') return props.products;
    return props.products.filter((p) => p.name.toLowerCase().includes(q) || (p.sku ?? '').toLowerCase().includes(q));
});

// --- Add supplier ---------------------------------------------------------

const supplierForm = useForm({ name: '', phone: '' });

function submitSupplier() {
    supplierForm.post('/purchases/suppliers', { preserveScroll: true, onSuccess: () => supplierForm.reset() });
}

// --- Purchase form -----------------------------------------------------

const form = useForm({
    supplier_id: props.suppliers[0]?.id ?? null,
    warehouse_id: props.warehouses[0]?.id ?? null,
    lines: [],
    payments: [],
});

watch(() => props.suppliers, (list) => {
    if (form.supplier_id === null && list.length > 0) form.supplier_id = list[0].id;
});

function addToCart(product) {
    const unit = product.units[0];
    form.lines.push({
        product_id: product.id,
        product_name: product.name,
        unit_id: unit.id,
        units: product.units,
        quantity: '1',
        unit_cost: '',
        discount: '0',
    });
}

function removeLine(index) {
    form.lines.splice(index, 1);
}

const subtotal = computed(() =>
    form.lines.reduce((sum, l) => sum + ((parseFloat(l.quantity) || 0) * (parseFloat(l.unit_cost) || 0) - (parseFloat(l.discount) || 0)), 0)
);
const paidTotal = computed(() => form.payments.reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0));
const balancePayable = computed(() => subtotal.value - paidTotal.value);

function addPaymentRow() {
    form.payments.push({ payment_method_id: props.paymentMethods[0]?.id ?? null, amount: '' });
}
function removePaymentRow(index) {
    form.payments.splice(index, 1);
}
function payInFull() {
    if (form.payments.length === 0) addPaymentRow();
    form.payments[0].amount = subtotal.value > 0 ? subtotal.value.toFixed(2) : '0.00';
}

function submitPurchase() {
    form.post('/purchases', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('lines', 'payments');
        },
    });
}

// --- Returns ------------------------------------------------------------

const returnForm = useForm({ purchase_id: null, purchase_item_id: null, quantity: '' });

function openReturn(purchase, item) {
    returnForm.purchase_id = purchase.id;
    returnForm.purchase_item_id = item.id;
    returnForm.quantity = '';
    activePanel.value = `return-${item.id}`;
}

function submitReturn() {
    returnForm.post('/purchases/returns', { preserveScroll: true, onSuccess: () => { activePanel.value = null; } });
}

function cancelPurchase(purchaseId) {
    router.post(`/purchases/${purchaseId}/cancel`, {}, { preserveScroll: true });
}

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);
}
</script>

<template>
    <AppLayout :title="t('purchases.title')">
        <main class="grid grid-cols-1 gap-6 p-6 lg:grid-cols-3">
            <!-- Product catalog -->
            <section class="lg:col-span-2">
                <div class="mb-4 flex gap-3">
                    <input v-model="search" type="text" :placeholder="t('purchases.search_placeholder')" class="w-full rounded-md border-stone-300 shadow-sm">
                    <select v-model="form.warehouse_id" class="rounded-md border-stone-300 shadow-sm">
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <button v-for="product in filteredProducts" :key="product.id" type="button" @click="addToCart(product)"
                        class="rounded-lg border border-stone-200 bg-white p-3 text-left shadow-sm hover:border-stone-400">
                        <p class="font-medium text-stone-900">{{ product.name }}</p>
                        <p class="text-xs text-stone-500">{{ product.sku ?? '—' }}</p>
                    </button>
                </div>

                <!-- Recent purchases -->
                <div class="mt-6 rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                    <h2 class="mb-3 text-base font-medium text-stone-900">{{ t('purchases.recent') }}</h2>
                    <div v-for="p in purchases" :key="p.id" class="mb-2 rounded-md border border-stone-200 p-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{ p.reference_no }} — {{ p.supplier }}</span>
                            <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs">{{ p.status }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-xs text-stone-500">
                            <span>{{ t('purchases.total_payable', { total: money(p.total), payable: money(p.balance_payable) }) }}</span>
                            <button v-if="p.status === 'confirmed'" type="button" @click="cancelPurchase(p.id)" class="text-red-600 underline">{{ t('purchases.cancel') }}</button>
                        </div>
                        <div v-for="item in p.items" :key="item.id">
                            <div class="mt-1 flex items-center justify-between border-t border-stone-100 pt-1 text-xs">
                                <span>{{ t('purchases.item_label', { id: item.id, qty: item.eligible_for_return }) }}</span>
                                <button
                                    v-if="p.status === 'confirmed' && parseFloat(item.eligible_for_return) > 0"
                                    type="button"
                                    @click="openReturn(p, item)"
                                    class="text-indigo-700 underline hover:text-indigo-800"
                                >
                                    {{ t('purchases.return') }}
                                </button>
                            </div>
                            <form v-if="activePanel === `return-${item.id}`" @submit.prevent="submitReturn" class="mt-2 flex gap-2">
                                <input v-model="returnForm.quantity" type="number" step="0.0001" :placeholder="t('purchases.qty_to_return')" class="w-32 rounded border-stone-300 text-xs">
                                <button type="submit" :disabled="returnForm.processing" class="rounded bg-indigo-600 px-2 py-1 text-xs text-white">{{ t('purchases.confirm_return') }}</button>
                            </form>
                        </div>
                    </div>
                    <p v-if="purchases.length === 0" class="text-sm text-stone-400">{{ t('common.none_yet') }}</p>
                </div>
            </section>

            <!-- Cart + checkout -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">{{ t('purchases.new_purchase') }}</h2>
                    <button
                        type="button"
                        @click="toggle('supplier')"
                        :aria-label="t('common.add')"
                        class="flex size-7 items-center justify-center rounded-full bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                        <PlusIcon class="size-3.5" />
                    </button>
                </div>

                <form v-if="activePanel === 'supplier'" @submit.prevent="submitSupplier" class="mb-3 flex gap-2 rounded-md border border-stone-200 p-2">
                    <input v-model="supplierForm.name" type="text" :placeholder="t('purchases.supplier_name_placeholder')" class="flex-1 rounded border-stone-300 text-xs">
                    <button type="submit" :aria-label="t('common.add')" class="flex items-center justify-center rounded bg-indigo-600 px-2 py-1 text-white">
                        <PlusIcon class="size-3.5" />
                    </button>
                </form>

                <label class="text-xs text-stone-500">{{ t('purchases.supplier') }}</label>
                <select v-model="form.supplier_id" class="mt-1 mb-3 w-full rounded-md border-stone-300 text-sm">
                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>

                <div v-if="form.lines.length === 0" class="text-sm text-stone-400">{{ t('purchases.no_items_yet') }}</div>

                <div v-for="(line, index) in form.lines" :key="index" class="mb-3 rounded-md border border-stone-200 p-2">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-stone-900">{{ line.product_name }}</span>
                        <button type="button" @click="removeLine(index)" :aria-label="t('common.remove')" class="flex size-6 items-center justify-center rounded text-red-600 hover:bg-red-50">
                            <TrashIcon class="size-3.5" />
                        </button>
                    </div>
                    <div class="grid grid-cols-4 gap-2 text-xs">
                        <select v-model="line.unit_id" class="rounded border-stone-300 text-xs">
                            <option v-for="u in line.units" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                        <input v-model="line.quantity" type="number" step="0.0001" :placeholder="t('purchases.qty')" class="rounded border-stone-300 text-xs">
                        <input v-model="line.unit_cost" type="number" step="0.01" :placeholder="t('purchases.unit_cost')" class="rounded border-stone-300 text-xs">
                        <input v-model="line.discount" type="number" step="0.01" :placeholder="t('purchases.discount')" class="rounded border-stone-300 text-xs">
                    </div>
                </div>

                <div class="flex justify-between text-sm">
                    <span>{{ t('purchases.subtotal') }}</span><span class="font-medium tabular-nums">{{ money(subtotal) }}</span>
                </div>

                <div class="mt-3 border-t border-stone-200 pt-3">
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-xs text-stone-500">{{ t('purchases.payments_optional') }}</label>
                        <button type="button" @click="payInFull" class="text-xs text-indigo-700 underline hover:text-indigo-800">{{ t('purchases.pay_in_full') }}</button>
                    </div>
                    <div v-for="(payment, i) in form.payments" :key="i" class="mb-2 flex gap-2">
                        <select v-model="payment.payment_method_id" class="w-1/2 rounded border-stone-300 text-xs">
                            <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                        <input v-model="payment.amount" type="number" step="0.01" :placeholder="t('pos.amount')" class="w-1/2 rounded border-stone-300 text-xs">
                        <button type="button" @click="removePaymentRow(i)" :aria-label="t('common.remove')" class="flex size-6 flex-shrink-0 items-center justify-center rounded text-red-600 hover:bg-red-50">
                            <TrashIcon class="size-3.5" />
                        </button>
                    </div>
                    <button
                        type="button"
                        @click="addPaymentRow"
                        :aria-label="t('common.add')"
                        class="flex size-6 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 hover:bg-indigo-200"
                    >
                        <PlusIcon class="size-3.5" />
                    </button>
                </div>

                <div class="mt-3 flex justify-between text-sm">
                    <span>{{ t('purchases.balance_payable') }}</span>
                    <span class="font-medium tabular-nums" :class="balancePayable > 0 ? 'text-amber-700' : 'text-emerald-700'">{{ money(balancePayable) }}</span>
                </div>

                <button type="button" :disabled="form.lines.length === 0 || form.processing" @click="submitPurchase"
                    class="mt-4 w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                    {{ t('purchases.confirm_purchase') }}
                </button>
            </section>
        </main>
    </AppLayout>
</template>
