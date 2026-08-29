<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Support\StockLevelService;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Actions\ConfirmSale;
use App\Modules\Sales\DTOs\CartLine;
use App\Modules\Sales\DTOs\PaymentAllocation;
use App\Modules\Sales\Exceptions\OverpaymentException;
use App\Modules\Sales\Exceptions\WalkInCreditNotAllowedException;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        $warehouses = Warehouse::where('status', 'active')->get(['id', 'name']);
        $stockService = app(StockLevelService::class);

        $products = Product::where('status', 'active')
            ->with(['baseUnit:id,name', 'unitConversions.unit:id,name'])
            ->get(['id', 'base_unit_id', 'name', 'sku'])
            ->map(function (Product $product) use ($stockService, $warehouses) {
                $stockByWarehouse = $warehouses->mapWithKeys(fn ($w) => [
                    $w->id => $stockService->currentStock($product->id, $w->id),
                ]);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'units' => [
                        ['id' => $product->baseUnit->id, 'name' => $product->baseUnit->name],
                        ...$product->unitConversions->map(fn ($c) => ['id' => $c->unit->id, 'name' => $c->unit->name]),
                    ],
                    'stock_by_warehouse' => $stockByWarehouse,
                ];
            });

        return Inertia::render('Pos/Index', [
            'products' => $products,
            'customers' => Customer::where('status', 'active')->get(['id', 'name', 'phone', 'balance']),
            'paymentMethods' => PaymentMethod::all(['id', 'name']),
            'warehouses' => $warehouses,
        ]);
    }

    public function store(Request $request, ConfirmSale $confirmSale): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'gte:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $lines = collect($validated['lines'])->map(fn ($line) => new CartLine(
            product: Product::findOrFail($line['product_id']),
            unitId: $line['unit_id'],
            quantity: (string) $line['quantity'],
            unitPrice: (string) $line['unit_price'],
            discount: (string) ($line['discount'] ?? '0.00'),
        ))->all();

        $payments = collect($validated['payments'])->map(fn ($payment) => new PaymentAllocation(
            paymentMethodId: $payment['payment_method_id'],
            amount: (string) $payment['amount'],
        ))->all();

        try {
            $sale = $confirmSale->handle(
                customerId: $validated['customer_id'] ?? null,
                warehouseId: $validated['warehouse_id'],
                cashierId: $request->user()->id,
                salesEmployeeId: null,
                lines: $lines,
                payments: $payments,
            );
        } catch (InsufficientStockException|OverpaymentException|WalkInCreditNotAllowedException $e) {
            return back()->withErrors(['sale' => $e->getMessage()])->withInput();
        }

        return redirect()->route('pos')->with('sale', [
            'reference_no' => $sale->reference_no,
            'total' => (string) $sale->total,
            'balance_due' => (string) $sale->balance_due,
        ]);
    }
}
