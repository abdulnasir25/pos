<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
    warehouses: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    purchases: { type: Array, default: () => [] },
});

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
    <AppLayout title="Purchases">
        <main class="grid grid-cols-1 gap-6 p-6 lg:grid-cols-3">
            <!-- Product catalog -->
            <section class="lg:col-span-2">
                <div class="mb-4 flex gap-3">
                    <input v-model="search" type="text" placeholder="Search products..." class="w-full rounded-md border-stone-300 shadow-sm">
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
                <div class="mt-6 rounded-lg bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-base font-medium text-stone-900">Recent Purchases</h2>
                    <div v-for="p in purchases" :key="p.id" class="mb-2 rounded-md border border-stone-200 p-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{ p.reference_no }} — {{ p.supplier }}</span>
                            <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs">{{ p.status }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-xs text-stone-500">
                            <span>Total {{ money(p.total) }} · Payable {{ money(p.balance_payable) }}</span>
                            <button v-if="p.status === 'confirmed'" type="button" @click="cancelPurchase(p.id)" class="text-red-600 underline">Cancel</button>
                        </div>
                        <div v-for="item in p.items" :key="item.id">
                            <div class="mt-1 flex items-center justify-between border-t border-stone-100 pt-1 text-xs">
                                <span>Item #{{ item.id }} — eligible for return: {{ item.eligible_for_return }}</span>
                                <button
                                    v-if="p.status === 'confirmed' && parseFloat(item.eligible_for_return) > 0"
                                    type="button"
                                    @click="openReturn(p, item)"
                                    class="text-stone-600 underline"
                                >
                                    Return
                                </button>
                            </div>
                            <form v-if="activePanel === `return-${item.id}`" @submit.prevent="submitReturn" class="mt-2 flex gap-2">
                                <input v-model="returnForm.quantity" type="number" step="0.0001" placeholder="Qty to return" class="w-32 rounded border-stone-300 text-xs">
                                <button type="submit" :disabled="returnForm.processing" class="rounded bg-stone-900 px-2 py-1 text-xs text-white">Confirm Return</button>
                            </form>
                        </div>
                    </div>
                    <p v-if="purchases.length === 0" class="text-sm text-stone-400">No purchases yet.</p>
                </div>
            </section>

            <!-- Cart + checkout -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-medium text-stone-900">New Purchase</h2>
                    <button type="button" @click="toggle('supplier')" class="text-xs text-stone-600 underline">+ New Supplier</button>
                </div>

                <form v-if="activePanel === 'supplier'" @submit.prevent="submitSupplier" class="mb-3 flex gap-2 rounded-md border border-stone-200 p-2">
                    <input v-model="supplierForm.name" type="text" placeholder="Supplier name" class="flex-1 rounded border-stone-300 text-xs">
                    <button type="submit" class="rounded bg-stone-900 px-2 py-1 text-xs text-white">Add</button>
                </form>

                <label class="text-xs text-stone-500">Supplier</label>
                <select v-model="form.supplier_id" class="mt-1 mb-3 w-full rounded-md border-stone-300 text-sm">
                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>

                <div v-if="form.lines.length === 0" class="text-sm text-stone-400">No items yet — click a product to add it.</div>

                <div v-for="(line, index) in form.lines" :key="index" class="mb-3 rounded-md border border-stone-200 p-2">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-stone-900">{{ line.product_name }}</span>
                        <button type="button" @click="removeLine(index)" class="text-xs text-red-600">Remove</button>
                    </div>
                    <div class="grid grid-cols-4 gap-2 text-xs">
                        <select v-model="line.unit_id" class="rounded border-stone-300 text-xs">
                            <option v-for="u in line.units" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                        <input v-model="line.quantity" type="number" step="0.0001" placeholder="Qty" class="rounded border-stone-300 text-xs">
                        <input v-model="line.unit_cost" type="number" step="0.01" placeholder="Unit cost" class="rounded border-stone-300 text-xs">
                        <input v-model="line.discount" type="number" step="0.01" placeholder="Discount" class="rounded border-stone-300 text-xs">
                    </div>
                </div>

                <div class="flex justify-between text-sm">
                    <span>Subtotal</span><span class="font-medium tabular-nums">{{ money(subtotal) }}</span>
                </div>

                <div class="mt-3 border-t border-stone-200 pt-3">
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-xs text-stone-500">Payments (optional — leave empty for fully on credit)</label>
                        <button type="button" @click="payInFull" class="text-xs text-stone-600 underline">Pay in full</button>
                    </div>
                    <div v-for="(payment, i) in form.payments" :key="i" class="mb-2 flex gap-2">
                        <select v-model="payment.payment_method_id" class="w-1/2 rounded border-stone-300 text-xs">
                            <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                        <input v-model="payment.amount" type="number" step="0.01" placeholder="Amount" class="w-1/2 rounded border-stone-300 text-xs">
                        <button type="button" @click="removePaymentRow(i)" class="text-xs text-red-600">✕</button>
                    </div>
                    <button type="button" @click="addPaymentRow" class="text-xs text-stone-600 underline">+ Add payment</button>
                </div>

                <div class="mt-3 flex justify-between text-sm">
                    <span>Balance payable</span>
                    <span class="font-medium tabular-nums" :class="balancePayable > 0 ? 'text-amber-700' : 'text-emerald-700'">{{ money(balancePayable) }}</span>
                </div>

                <button type="button" :disabled="form.lines.length === 0 || form.processing" @click="submitPurchase"
                    class="mt-4 w-full rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 disabled:opacity-50">
                    Confirm Purchase
                </button>
            </section>
        </main>
    </AppLayout>
</template>
