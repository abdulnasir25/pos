<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Sales\Actions\CancelSale;
use App\Modules\Sales\Actions\ReturnSaleItems;
use App\Modules\Sales\DTOs\ReturnLine;
use App\Modules\Sales\Exceptions\InvalidSaleStateException;
use App\Modules\Sales\Exceptions\ReturnQuantityExceedsAvailableException;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Support\ReceiptBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        $sales = Sale::orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'reference_no' => $sale->reference_no,
                'customer' => $sale->customer_id !== null ? Customer::find($sale->customer_id)?->name : null,
                'total' => (string) $sale->total,
                'status' => $sale->status->value,
                'confirmed_at' => $sale->confirmed_at?->toDateTimeString(),
            ]);

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
        ]);
    }

    public function showOne(Sale $sale): Response
    {
        $sale->loadMissing('items', 'payments', 'returns.items');
        $receipt = app(ReceiptBuilder::class)->build($sale);

        return Inertia::render('Sales/Show', [
            'sale' => [
                'id' => $sale->id,
                'status' => $sale->status->value,
                'cancelled_at' => $sale->cancelled_at?->toDateTimeString(),
            ],
            'receipt' => [
                'reference_no' => $receipt->referenceNo,
                'issued_at' => $receipt->issuedAt,
                'customer_name' => $receipt->customerName,
                'subtotal' => $receipt->subtotal,
                'discount_total' => $receipt->discountTotal,
                'total' => $receipt->total,
                'payments' => $receipt->payments,
                'paid_total' => $receipt->paidTotal,
                'balance_due' => $receipt->balanceDue,
            ],
            'items' => $sale->items->map(fn ($item) => [
                'id' => $item->id,
                'product' => Product::find($item->product_id)?->name,
                'unit' => Unit::find($item->unit_id)?->name,
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'line_total' => (string) $item->line_total,
                'eligible_for_return' => $item->quantityEligibleForReturn(),
            ]),
            'returns' => $sale->returns->map(fn ($return) => [
                'id' => $return->id,
                'refund_amount' => (string) $return->refund_amount,
                'notes' => $return->notes,
                'created_at' => $return->created_at->toDateTimeString(),
            ]),
        ]);
    }

    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        try {
            app(CancelSale::class)->handle($sale, $request->user()->id);
        } catch (InvalidSaleStateException $e) {
            return back()->withErrors(['sale' => $e->getMessage()]);
        }

        return back()->with('success', 'Sale cancelled — stock and customer balance restored.');
    }

    public function storeReturn(Request $request, Sale $sale): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sale_item_id' => ['required', 'integer', 'exists:sale_items,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $lines = collect($validated['lines'])->map(fn ($line) => new ReturnLine(
            saleItemId: $line['sale_item_id'],
            quantity: (string) $line['quantity'],
        ))->all();

        try {
            app(ReturnSaleItems::class)->handle($sale, $lines, $request->user()->id, $validated['notes'] ?? null);
        } catch (InvalidSaleStateException|ReturnQuantityExceedsAvailableException $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }

        return back()->with('success', 'Return processed — stock and customer balance updated.');
    }
}
