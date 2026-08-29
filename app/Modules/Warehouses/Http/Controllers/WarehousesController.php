<?php

namespace App\Modules\Warehouses\Http\Controllers;

use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehousesController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        return Inertia::render('Warehouses/Index', [
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:warehouses,name'],
        ]);

        Warehouse::create([...$validated, 'status' => 'active']);

        return back()->with('success', 'Warehouse added.');
    }
}
