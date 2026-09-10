<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    invoice: { type: Object, required: true },
    shareUrl: { type: String, default: null },
    viewerIsLandlord: { type: Boolean, default: false },
});

function money(n) {
    return (Math.round(parseFloat(n ?? 0) * 100) / 100).toFixed(2);
}

const statusLabel = {
    pending: 'Pending',
    paid: 'Paid',
    overdue: 'Overdue',
};

function printInvoice() {
    window.print();
}

const copied = ref(false);

async function copyShareLink() {
    try {
        await navigator.clipboard.writeText(props.shareUrl);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch {
        window.prompt('Copy this link:', props.shareUrl);
    }
}
</script>

<template>
    <Head :title="`Invoice ${invoice.reference}`" />

    <div class="min-h-screen bg-stone-100 py-10 print:bg-white print:py-0">
        <div class="mx-auto max-w-2xl print:max-w-none">
            <div class="mb-4 flex items-center justify-between print:hidden">
                <Link v-if="viewerIsLandlord" href="/landlord/billing/invoices" class="text-sm text-indigo-700 underline hover:text-indigo-800">
                    ← Back to Invoices
                </Link>
                <span v-else></span>
                <div class="flex items-center gap-3">
                    <button
                        v-if="viewerIsLandlord"
                        type="button"
                        @click="copyShareLink"
                        class="rounded-lg border border-indigo-200 px-3 py-1.5 text-sm font-medium text-indigo-700 hover:bg-indigo-50"
                    >
                        {{ copied ? 'Link copied!' : 'Copy share link' }}
                    </button>
                    <button
                        type="button"
                        @click="printInvoice"
                        class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        Print / Save as PDF
                    </button>
                </div>
            </div>

            <div class="rounded-xl border border-stone-200/70 bg-white p-10 shadow-sm print:rounded-none print:border-0 print:p-0 print:shadow-none">
                <div class="flex items-start justify-between border-b border-stone-200 pb-6">
                    <div class="flex items-center gap-3">
                        <span class="flex size-11 flex-shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-400 to-indigo-600 text-sm font-semibold text-white">L&amp;L</span>
                        <div>
                            <p class="text-lg font-semibold text-stone-900">Ledger &amp; Loom</p>
                            <p class="text-xs text-stone-500">Point of sale &amp; business management</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xl font-semibold text-stone-900">Invoice</p>
                        <p class="text-sm text-stone-500">{{ invoice.reference }}</p>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-6 text-sm">
                    <div>
                        <p class="mb-1 text-xs uppercase tracking-wide text-stone-400">Billed to</p>
                        <p class="font-medium text-stone-900">{{ invoice.tenant }}</p>
                        <p class="text-stone-500">{{ invoice.tenant_subdomain }}.pos.test</p>
                    </div>
                    <div class="text-right">
                        <p class="mb-1 text-xs uppercase tracking-wide text-stone-400">Status</p>
                        <span
                            class="inline-block rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="{
                                'bg-emerald-100 text-emerald-800': invoice.status === 'paid',
                                'bg-amber-100 text-amber-800': invoice.status === 'pending',
                                'bg-red-100 text-red-800': invoice.status === 'overdue',
                            }"
                        >{{ statusLabel[invoice.status] ?? invoice.status }}</span>
                        <p v-if="invoice.paid_at" class="mt-1 text-xs text-stone-500">Paid on {{ invoice.paid_at }}</p>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-3 gap-6 text-sm">
                    <div>
                        <p class="mb-1 text-xs uppercase tracking-wide text-stone-400">Issued</p>
                        <p class="text-stone-900">{{ invoice.issued_at }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-xs uppercase tracking-wide text-stone-400">Due</p>
                        <p class="text-stone-900">{{ invoice.due_date }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-xs uppercase tracking-wide text-stone-400">Billing period</p>
                        <p class="text-stone-900">{{ invoice.period_start }} – {{ invoice.period_end }}</p>
                    </div>
                </div>

                <table class="mt-8 w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-left text-xs uppercase tracking-wide text-stone-400">
                            <th class="pb-2">Description</th>
                            <th class="pb-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-stone-100">
                            <td class="py-3 text-stone-900">
                                {{ invoice.plan }} plan
                                <span class="block text-xs text-stone-500">{{ invoice.period_start }} – {{ invoice.period_end }}</span>
                            </td>
                            <td class="py-3 text-right tabular-nums text-stone-900">{{ money(invoice.amount) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="pt-4 text-right text-sm font-medium text-stone-500">Total</td>
                            <td class="pt-4 text-right text-lg font-semibold tabular-nums text-stone-900">{{ money(invoice.amount) }}</td>
                        </tr>
                    </tfoot>
                </table>

                <p class="mt-10 text-center text-xs text-stone-400">Thank you for using Ledger &amp; Loom.</p>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    @page {
        margin: 1.5cm;
    }
}
</style>
