<?php

namespace App\Modules\Sales\Support;

use App\Modules\Customers\Models\Customer;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Sales\DTOs\Receipt;
use App\Modules\Sales\Models\Sale;

class ReceiptBuilder
{
    public function build(Sale $sale): Receipt
    {
        $sale->loadMissing(['items', 'payments']);

        $customerName = $sale->customer_id !== null
            ? Customer::find($sale->customer_id)?->name ?? 'Walk-in'
            : 'Walk-in';

        $lines = $sale->items->map(fn ($item) => [
            'product' => Product::find($item->product_id)?->name,
            'unit' => Unit::find($item->unit_id)?->name,
            'quantity' => (string) $item->quantity,
            'unit_price' => (string) $item->unit_price,
            'discount' => (string) $item->discount,
            'line_total' => (string) $item->line_total,
        ])->all();

        $payments = $sale->payments->map(fn ($payment) => [
            'method' => PaymentMethod::find($payment->payment_method_id)?->name,
            'amount' => (string) $payment->amount,
        ])->all();

        return new Receipt(
            referenceNo: $sale->reference_no,
            issuedAt: $sale->confirmed_at?->toDateTimeString() ?? '',
            customerName: $customerName,
            lines: $lines,
            subtotal: (string) $sale->subtotal,
            discountTotal: (string) $sale->discount_total,
            total: (string) $sale->total,
            payments: $payments,
            paidTotal: (string) $sale->paid_total,
            balanceDue: (string) $sale->balance_due,
        );
    }
}
