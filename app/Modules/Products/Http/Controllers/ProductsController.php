<?php

namespace App\Modules\Products\Http\Controllers;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Products\Models\UnitConversion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductsController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        $units = Unit::orderBy('name')->get(['id', 'name', 'abbreviation']);

        $products = Product::with(['baseUnit:id,name,abbreviation', 'unitConversions.unit:id,name,abbreviation'])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'status' => $product->status,
                'low_stock_threshold' => $product->low_stock_threshold,
                'base_unit' => $product->baseUnit->name,
                'conversions' => $product->unitConversions->map(fn (UnitConversion $c) => [
                    'unit' => $c->unit->name,
                    'factor' => $c->factor,
                ]),
            ]);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'units' => $units,
        ]);
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:30', 'unique:units,name'],
            'abbreviation' => ['nullable', 'string', 'max:10'],
        ]);

        Unit::create($validated);

        return back()->with('success', 'Unit added.');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'base_unit_id' => ['required', 'integer', 'exists:units,id'],
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:64', 'unique:products,sku'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
        ]);

        Product::create([...$validated, 'status' => 'active']);

        return back()->with('success', 'Product added.');
    }

    public function storeConversion(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'factor' => ['required', 'numeric', 'gt:0'],
        ]);

        if ($validated['unit_id'] === $product->base_unit_id) {
            return back()->withErrors(['conversion' => "That is already the product's base unit."])->withInput();
        }

        if (UnitConversion::where('product_id', $product->id)->where('unit_id', $validated['unit_id'])->exists()) {
            return back()->withErrors(['conversion' => 'This unit is already configured for this product.'])->withInput();
        }

        UnitConversion::create([
            'product_id' => $product->id,
            'unit_id' => $validated['unit_id'],
            'factor' => $validated['factor'],
        ]);

        return back()->with('success', 'Alternate unit added.');
    }
}
