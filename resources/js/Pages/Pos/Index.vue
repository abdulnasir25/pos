<script setup>
import { computed, ref } from 'vue';
import { usePage, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    warehouses: { type: Array, default: () => [] },
});

const page = usePage();

const search = ref('');
const filteredProducts = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (q === '') return props.products;
    return props.products.filter((p) =>
        p.name.toLowerCase().includes(q) || (p.sku ?? '').toLowerCase().includes(q)
    );
});

const form = useForm({
    customer_id: null,
    warehouse_id: props.warehouses[0]?.id ?? null,
    lines: [],
    payments: [{ payment_method_id: props.paymentMethods[0]?.id ?? null, amount: '' }],
});

function stockFor(product) {
    if (!form.warehouse_id) return '0.0000';
    return product.stock_by_warehouse[form.warehouse_id] ?? '0.0000';
}

function addToCart(product) {
    const unit = product.units[0];
    form.lines.push({
        product_id: product.id,
        product_name: product.name,
        unit_id: unit.id,
        units: product.units,
        quantity: '1',
        unit_price: '',
        discount: '0',
    });
}

function removeLine(index) {
    form.lines.splice(index, 1);
}

const subtotal = computed(() =>
    form.lines.reduce((sum, line) => {
        const qty = parseFloat(line.quantity) || 0;
        const price = parseFloat(line.unit_price) || 0;
        const discount = parseFloat(line.discount) || 0;
        return sum + (qty * price - discount);
    }, 0)
);

const paidTotal = computed(() =>
    form.payments.reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0)
);

const balanceDue = computed(() => subtotal.value - paidTotal.value);

function addPaymentRow() {
    form.payments.push({ payment_method_id: props.paymentMethods[0]?.id ?? null, amount: '' });
}

function removePaymentRow(index) {
    form.payments.splice(index, 1);
}

function payExactBalance() {
    if (form.payments.length === 0) addPaymentRow();
    form.payments[0].amount = balanceDue.value > 0 ? balanceDue.value.toFixed(2) : '0.00';
}

function submitSale() {
    form.post('/pos/sale', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('lines');
            form.payments = [{ payment_method_id: props.paymentMethods[0]?.id ?? null, amount: '' }];
        },
    });
}

function money(n) {
    return (Math.round(n * 100) / 100).toFixed(2);
}
</script>

<template>
    <AppLayout title="Point of Sale">
        <div
            v-if="page.props.flash?.sale"
            class="mx-6 mt-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            Sale <strong>{{ page.props.flash.sale.reference_no }}</strong> confirmed — total
            {{ money(page.props.flash.sale.total) }}, balance due {{ money(page.props.flash.sale.balance_due) }}.
        </div>

        <div
            v-if="page.props.errors?.sale"
            class="mx-6 mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800"
        >
            {{ page.props.errors.sale }}
        </div>

        <main class="grid grid-cols-1 gap-6 p-6 lg:grid-cols-3">
            <!-- Product catalog -->
            <section class="lg:col-span-2">
                <div class="mb-4 flex gap-3">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search products by name or SKU..."
                        class="w-full rounded-md border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500"
                    >
                    <select
                        v-model="form.warehouse_id"
                        class="rounded-md border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500"
                    >
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <button
                        v-for="product in filteredProducts"
                        :key="product.id"
                        type="button"
                        @click="addToCart(product)"
                        class="rounded-lg border border-stone-200 bg-white p-3 text-left shadow-sm hover:border-stone-400"
                    >
                        <p class="font-medium text-stone-900">{{ product.name }}</p>
                        <p class="text-xs text-stone-500">{{ product.sku ?? '—' }}</p>
                        <p class="mt-1 text-xs text-stone-600">Stock: {{ stockFor(product) }}</p>
                    </button>
                    <p v-if="filteredProducts.length === 0" class="col-span-full text-sm text-stone-400">
                        No products match "{{ search }}".
                    </p>
                </div>
            </section>

            <!-- Cart + checkout -->
            <section class="rounded-xl border border-stone-200/70 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-base font-medium text-stone-900">Cart</h2>

                <div v-if="form.lines.length === 0" class="text-sm text-stone-400">
                    No items yet — click a product to add it.
                </div>

                <div v-for="(line, index) in form.lines" :key="index" class="mb-3 rounded-md border border-stone-200 p-2">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-stone-900">{{ line.product_name }}</span>
                        <button type="button" @click="removeLine(index)" class="text-xs text-red-600 hover:text-red-800">Remove</button>
                    </div>
                    <div class="grid grid-cols-4 gap-2 text-xs">
                        <div>
                            <label class="text-stone-500">Unit</label>
                            <select v-model="line.unit_id" class="mt-0.5 w-full rounded border-stone-300 text-xs">
                                <option v-for="u in line.units" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-stone-500">Qty</label>
                            <input v-model="line.quantity" type="number" step="0.0001" min="0.0001" class="mt-0.5 w-full rounded border-stone-300 text-xs">
                        </div>
                        <div>
                            <label class="text-stone-500">Price</label>
                            <input v-model="line.unit_price" type="number" step="0.01" min="0" class="mt-0.5 w-full rounded border-stone-300 text-xs">
                        </div>
                        <div>
                            <label class="text-stone-500">Discount</label>
                            <input v-model="line.discount" type="number" step="0.01" min="0" class="mt-0.5 w-full rounded border-stone-300 text-xs">
                        </div>
                    </div>
                    <p
                        v-if="form.errors[`lines.${index}.quantity`] || form.errors[`lines.${index}.unit_price`]"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ form.errors[`lines.${index}.quantity`] || form.errors[`lines.${index}.unit_price`] }}
                    </p>
                </div>

                <div class="mt-4 border-t border-stone-200 pt-3">
                    <label class="text-xs text-stone-500">Customer (optional — leave blank for walk-in)</label>
                    <select v-model="form.customer_id" class="mt-1 w-full rounded-md border-stone-300 text-sm">
                        <option :value="null">Walk-in</option>
                        <option v-for="c in customers" :key="c.id" :value="c.id">
                            {{ c.name }}{{ Number(c.balance) > 0 ? ` (owes ${money(c.balance)})` : '' }}
                        </option>
                    </select>
                </div>

                <div class="mt-3 flex justify-between text-sm text-stone-700">
                    <span>Subtotal</span>
                    <span class="font-medium tabular-nums">{{ money(subtotal) }}</span>
                </div>

                <div class="mt-3 border-t border-stone-200 pt-3">
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-xs text-stone-500">Payments</label>
                        <button type="button" @click="payExactBalance" class="text-xs text-indigo-700 underline hover:text-indigo-800">Pay exact balance</button>
                    </div>
                    <div v-for="(payment, index) in form.payments" :key="index" class="mb-2 flex gap-2">
                        <select v-model="payment.payment_method_id" class="w-1/2 rounded border-stone-300 text-xs">
                            <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                        <input v-model="payment.amount" type="number" step="0.01" min="0" placeholder="Amount" class="w-1/2 rounded border-stone-300 text-xs">
                        <button
                            v-if="form.payments.length > 1"
                            type="button"
                            @click="removePaymentRow(index)"
                            class="text-xs text-red-600"
                        >
                            ✕
                        </button>
                    </div>
                    <button type="button" @click="addPaymentRow" class="text-xs text-indigo-700 underline hover:text-indigo-800">+ Add payment method</button>
                </div>

                <div class="mt-3 flex justify-between text-sm">
                    <span class="text-stone-700">Paid</span>
                    <span class="font-medium tabular-nums">{{ money(paidTotal) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-stone-700">Balance due</span>
                    <span
                        class="font-medium tabular-nums"
                        :class="balanceDue > 0 ? 'text-amber-700' : 'text-emerald-700'"
                    >
                        {{ money(balanceDue) }}
                    </span>
                </div>

                <button
                    type="button"
                    :disabled="form.lines.length === 0 || form.processing"
                    @click="submitSale"
                    class="mt-4 w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    Confirm Sale
                </button>
            </section>
        </main>
    </AppLayout>
</template>
