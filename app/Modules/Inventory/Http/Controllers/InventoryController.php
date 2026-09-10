<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Actions\RecordAdjustment;
use App\Modules\Inventory\Actions\RecordDamage;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Support\StockLevelService;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Warehouses\Models\Warehouse;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        $warehouses = Warehouse::where('status', 'active')->get(['id', 'name']);
        $stockService = app(StockLevelService::class);

        $products = Product::where('status', 'active')
            ->with(['baseUnit:id,name', 'unitConversions.unit:id,name'])
            ->get(['id', 'base_unit_id', 'name'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'units' => [
                    ['id' => $product->baseUnit->id, 'name' => $product->baseUnit->name],
                    ...$product->unitConversions->map(fn ($c) => ['id' => $c->unit->id, 'name' => $c->unit->name]),
                ],
                'stock_by_warehouse' => $warehouses->mapWithKeys(fn ($w) => [
                    $w->id => $stockService->currentStock($product->id, $w->id),
                ]),
            ]);

        $movements = InventoryMovement::orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (InventoryMovement $movement) => [
                'id' => $movement->id,
                'product' => Product::find($movement->product_id)?->name ?? '—',
                'warehouse' => Warehouse::find($movement->warehouse_id)?->name ?? '—',
                'reason' => $movement->reason->value,
                'quantity' => (string) $movement->quantity_base_unit,
                'created_by' => $movement->created_by !== null ? User::find($movement->created_by)?->name : null,
                'created_at' => $movement->created_at->toDateTimeString(),
            ]);

        return Inertia::render('Inventory/Index', [
            'products' => $products,
            'warehouses' => $warehouses,
            'movements' => $movements,
        ]);
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'reason' => ['required', 'in:found,shrinkage,damage'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $product = Product::findOrFail($validated['product_id']);

        try {
            if ($validated['reason'] === 'damage') {
                app(RecordDamage::class)->handle(
                    $product,
                    $validated['warehouse_id'],
                    $validated['unit_id'],
                    (string) $validated['quantity'],
                    $request->user()->id,
                );
            } else {
                $signedQuantity = $validated['reason'] === 'shrinkage'
                    ? bcmul((string) $validated['quantity'], '-1', 4)
                    : (string) $validated['quantity'];

                app(RecordAdjustment::class)->handle(
                    $product,
                    $validated['warehouse_id'],
                    $validated['unit_id'],
                    $signedQuantity,
                    $request->user()->id,
                );
            }
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['adjustment' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Stock adjustment recorded.');
    }
}
