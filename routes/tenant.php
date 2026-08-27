<?php

use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant routes
|--------------------------------------------------------------------------
|
| Registered under the `tenant` middleware alias (see bootstrap/app.php),
| so every route here already has a resolved TenantContext and a default
| DB connection pointed at that tenant's own database before it runs.
|
*/

Route::get('/_tenant/whoami', function (TenantContext $tenants) {
    $tenant = $tenants->get();

    return response()->json([
        'tenant' => $tenant->slug,
        'name' => $tenant->name,
        'status' => $tenant->status,
        'connection' => config('database.default'),
    ]);
});
