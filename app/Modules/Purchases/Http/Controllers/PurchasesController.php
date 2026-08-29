<?php

namespace App\Modules\Purchases\Http\Controllers;

use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Products\Models\Product;
use App\Modules\Purchases\Actions\CancelPurchase;
use App\Modules\Purchases\Actions\ConfirmPurchase;
use App\Modules\Purchases\Actions\ReturnPurchaseItems;
use App\Modules\Purchases\DTOs\PurchaseLine;
use App\Modules\Purchases\DTOs\PurchasePaymentAllocation;
use App\Modules\Purchases\DTOs\PurchaseReturnLine;
use App\Modules\Purchases\Exceptions\InvalidPurchaseStateException;
use App\Modules\Purchases\Exceptions\OverpaymentException;
use App\Modules\Purchases\Exceptions\ReturnQuantityExceedsAvailableException;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchasesController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        $products = Product::where('status', 'active')
            ->with(['baseUnit:id,name', 'unitConversions.unit:id,name'])
            ->get(['id', 'base_unit_id', 'name', 'sku'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'units' => [
                    ['id' => $product->baseUnit->id, 'name' => $product->baseUnit->name],
                    ...$product->unitConversions->map(fn ($c) => ['id' => $c->unit->id, 'name' => $c->unit->name]),
                ],
            ]);

        $purchases = Purchase::with('items')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (Purchase $purchase) {
                $supplier = Supplier::find($purchase->supplier_id);

                return [
                    'id' => $purchase->id,
                    'reference_no' => $purchase->reference_no,
                    'supplier' => $supplier?->name,
                    'total' => (string) $purchase->total,
                    'balance_payable' => (string) $purchase->balance_payable,
                    'status' => $purchase->status->value,
                    'items' => $purchase->items->map(fn ($item) => [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => (string) $item->quantity,
                        'eligible_for_return' => $item->quantityEligibleForReturn(),
                    ]),
                ];
            });

        return Inertia::render('Purchases/Index', [
            'products' => $products,
            'suppliers' => Supplier::where('status', 'active')->get(['id', 'name', 'phone', 'balance']),
            'warehouses' => Warehouse::where('status', 'active')->get(['id', 'name']),
            'paymentMethods' => PaymentMethod::all(['id', 'name']),
            'purchases' => $purchases,
        ]);
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        Supplier::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'balance' => '0.00',
            'status' => 'active',
        ]);

        return back()->with('success', 'Supplier added.');
    }

    public function store(Request $request, ConfirmPurchase $confirmPurchase): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'gte:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'gte:0'],
            'payments' => ['present', 'array'],
            'payments.*.payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $lines = collect($validated['lines'])->map(fn ($line) => new PurchaseLine(
            product: Product::findOrFail($line['product_id']),
            unitId: $line['unit_id'],
            quantity: (string) $line['quantity'],
            unitCost: (string) $line['unit_cost'],
            discount: (string) ($line['discount'] ?? '0.00'),
        ))->all();

        $payments = collect($validated['payments'] ?? [])->map(fn ($payment) => new PurchasePaymentAllocation(
            paymentMethodId: $payment['payment_method_id'],
            amount: (string) $payment['amount'],
        ))->all();

        try {
            $purchase = $confirmPurchase->handle(
                supplierId: $validated['supplier_id'],
                warehouseId: $validated['warehouse_id'],
                employeeId: null,
                createdBy: $request->user()->id,
                lines: $lines,
                payments: $payments,
            );
        } catch (InsufficientStockException|OverpaymentException $e) {
            return back()->withErrors(['purchase' => $e->getMessage()])->withInput();
        }

        return redirect()->route('purchases')->with('success', "Purchase {$purchase->reference_no} confirmed.");
    }

    public function cancel(Request $request, Purchase $purchase): RedirectResponse
    {
        try {
            app(CancelPurchase::class)->handle($purchase, $request->user()->id);
        } catch (InvalidPurchaseStateException $e) {
            return back()->withErrors(['purchase' => $e->getMessage()]);
        }

        return back()->with('success', 'Purchase cancelled.');
    }

    public function storeReturn(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_id' => ['required', 'integer', 'exists:purchases,id'],
            'purchase_item_id' => ['required', 'integer', 'exists:purchase_items,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $purchase = Purchase::findOrFail($validated['purchase_id']);

        try {
            app(ReturnPurchaseItems::class)->handle(
                $purchase,
                [new PurchaseReturnLine($validated['purchase_item_id'], (string) $validated['quantity'])],
                $request->user()->id,
            );
        } catch (InvalidPurchaseStateException|ReturnQuantityExceedsAvailableException $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }

        return back()->with('success', 'Return recorded.');
    }
}
