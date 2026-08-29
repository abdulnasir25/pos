<?php

use App\Modules\Access\Http\Controllers\DashboardController;
use App\Modules\Access\Http\Controllers\LoginController;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant routes
|--------------------------------------------------------------------------
|
| Registered under 'web', 'tenant', and HandleInertiaRequests (see
| bootstrap/app.php), so every route here already has a session, CSRF
| protection, a resolved TenantContext, a DB connection pointed at that
| tenant's own database, and Inertia-shared auth data before it runs.
|
*/

Route::get('/login', [LoginController::class, 'show'])->middleware('guest')->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('guest');
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth');

Route::get('/dashboard', [DashboardController::class, 'show'])->middleware('auth')->name('dashboard');

Route::get('/_tenant/whoami', function (TenantContext $tenants) {
    $tenant = $tenants->get();

    return response()->json([
        'tenant' => $tenant->slug,
        'name' => $tenant->name,
        'status' => $tenant->status,
        'connection' => config('database.default'),
    ]);
});
