<?php

use App\Modules\Access\Http\Controllers\DashboardController;
use App\Modules\Access\Http\Controllers\LoginController;
use App\Modules\Accounting\Http\Controllers\AccountingController;
use App\Modules\AuditLog\Http\Controllers\AuditLogController;
use App\Modules\CashRegister\Http\Controllers\CashRegisterController;
use App\Modules\Commission\Http\Controllers\CommissionController;
use App\Modules\Expenses\Http\Controllers\ExpensesController;
use App\Modules\Partners\Http\Controllers\PartnersController;
use App\Modules\Purchases\Http\Controllers\PurchasesController;
use App\Modules\Reports\Http\Controllers\ReportsController;
use App\Modules\Sales\Http\Controllers\PosController;
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

Route::get('/pos', [PosController::class, 'show'])->middleware(['auth', 'permission:sales.create'])->name('pos');
Route::post('/pos/sale', [PosController::class, 'store'])->middleware(['auth', 'permission:sales.create'])->name('pos.sale');

Route::middleware(['auth', 'permission:partners.manage'])->group(function () {
    Route::get('/partners', [PartnersController::class, 'show'])->name('partners');
    Route::post('/partners', [PartnersController::class, 'storePartner'])->name('partners.store');
    Route::post('/partners/rebalance', [PartnersController::class, 'storeRebalance'])->name('partners.rebalance');
    Route::post('/partners/capital', [PartnersController::class, 'storeCapital'])->name('partners.capital');
    Route::post('/partners/loans', [PartnersController::class, 'storeLoan'])->name('partners.loans');
    Route::post('/partners/repayments', [PartnersController::class, 'storeRepayment'])->name('partners.repayments');
});

Route::middleware(['auth', 'permission:expenses.manage'])->group(function () {
    Route::get('/expenses', [ExpensesController::class, 'show'])->name('expenses');
    Route::post('/expenses/categories', [ExpensesController::class, 'storeCategory'])->name('expenses.categories.store');
    Route::post('/expenses', [ExpensesController::class, 'storeExpense'])->name('expenses.store');
    Route::post('/expenses/corrections', [ExpensesController::class, 'storeCorrection'])->name('expenses.corrections.store');
});

Route::middleware(['auth', 'permission:commission.manage'])->group(function () {
    Route::get('/commission', [CommissionController::class, 'show'])->name('commission');
    Route::post('/commission/rules', [CommissionController::class, 'storeRule'])->name('commission.rules.store');
    Route::post('/commission/periods', [CommissionController::class, 'storePeriod'])->name('commission.periods.store');
    Route::post('/commission/calculate', [CommissionController::class, 'calculate'])->name('commission.calculate');
    Route::post('/commission/entries/{entry}/approve', [CommissionController::class, 'approve'])->name('commission.entries.approve');
    Route::post('/commission/entries/{entry}/finalize', [CommissionController::class, 'finalize'])->name('commission.entries.finalize');
    Route::post('/commission/entries/{entry}/pay', [CommissionController::class, 'pay'])->name('commission.entries.pay');
    Route::post('/commission/corrections', [CommissionController::class, 'storeCorrection'])->name('commission.corrections.store');
});

Route::get('/reports', [ReportsController::class, 'show'])->middleware(['auth', 'permission:reports.view'])->name('reports');

Route::middleware(['auth', 'permission:cash_register.manage'])->group(function () {
    Route::get('/cash-register', [CashRegisterController::class, 'show'])->name('cash-register');
    Route::post('/cash-register/accounts', [CashRegisterController::class, 'storeAccount'])->name('cash-register.accounts.store');
    Route::post('/cash-register/sessions', [CashRegisterController::class, 'openSession'])->name('cash-register.sessions.open');
    Route::post('/cash-register/sessions/{session}/close', [CashRegisterController::class, 'closeSession'])->name('cash-register.sessions.close');
});

Route::get('/audit-log', [AuditLogController::class, 'show'])->middleware(['auth', 'permission:audit_logs.view'])->name('audit-log');

Route::middleware(['auth', 'permission:purchases.manage'])->group(function () {
    Route::get('/purchases', [PurchasesController::class, 'show'])->name('purchases');
    Route::post('/purchases/suppliers', [PurchasesController::class, 'storeSupplier'])->name('purchases.suppliers.store');
    Route::post('/purchases', [PurchasesController::class, 'store'])->name('purchases.store');
    Route::post('/purchases/{purchase}/cancel', [PurchasesController::class, 'cancel'])->name('purchases.cancel');
    Route::post('/purchases/returns', [PurchasesController::class, 'storeReturn'])->name('purchases.returns.store');
});

Route::middleware(['auth', 'permission:accounting.view'])->group(function () {
    Route::get('/accounting', [AccountingController::class, 'show'])->name('accounting');
    Route::post('/accounting/accounts', [AccountingController::class, 'storeAccount'])->name('accounting.accounts.store');
    Route::post('/accounting/entries', [AccountingController::class, 'storeEntry'])->name('accounting.entries.store');
});

Route::get('/_tenant/whoami', function (TenantContext $tenants) {
    $tenant = $tenants->get();

    return response()->json([
        'tenant' => $tenant->slug,
        'name' => $tenant->name,
        'status' => $tenant->status,
        'connection' => config('database.default'),
    ]);
});
