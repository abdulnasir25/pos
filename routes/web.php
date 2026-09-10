<?php

use App\Modules\Platform\Http\Controllers\BillingController;
use App\Modules\Platform\Http\Controllers\LandlordLoginController;
use App\Modules\Platform\Http\Controllers\TenantsController;
use App\Modules\Platform\Http\Middleware\HandleLandlordInertiaRequests;
use App\Modules\Tenancy\Support\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * This route has no domain constraint, so it also matches every tenant
 * subdomain (alfateh.pos.test/, etc.) — there is nothing else registered
 * for a bare "/" on those hosts, and none of the tenant-scoped
 * middleware (IdentifyTenant, HandleInertiaRequests) has run here. A
 * tenant subdomain never gets the landlord welcome page: it's bounced
 * straight to its own login, which resolves the tenant correctly and,
 * if already signed in, redirects on to the dashboard on its own.
 */
Route::get('/', function (Request $request, TenantResolver $resolver) {
    if ($resolver->resolve($request) !== null) {
        return redirect('/login');
    }

    return view('welcome');
});

Route::middleware(HandleLandlordInertiaRequests::class)->group(function () {
    Route::get('/landlord/login', [LandlordLoginController::class, 'show'])->middleware('guest:landlord')->name('landlord.login');
    Route::post('/landlord/login', [LandlordLoginController::class, 'store'])->middleware('guest:landlord');
    Route::post('/landlord/logout', [LandlordLoginController::class, 'destroy'])->middleware('auth:landlord');

    // Signed, no landlord login required — this is the link a tenant
    // receives to view/print/save their own invoice. The signature
    // (with its own expiry) is the only thing standing in for auth
    // here, so this must stay outside the auth:landlord group.
    Route::get('/landlord/billing/invoices/{invoice}/shared', [BillingController::class, 'showSharedInvoice'])
        ->middleware('signed')
        ->name('landlord.billing.invoices.shared');

    Route::middleware('auth:landlord')->group(function () {
        Route::get('/landlord/tenants', [TenantsController::class, 'show'])->name('landlord.tenants');
        Route::post('/landlord/tenants', [TenantsController::class, 'store'])->name('landlord.tenants.store');
        Route::post('/landlord/tenants/{tenant}/toggle-status', [TenantsController::class, 'toggleStatus'])->name('landlord.tenants.toggle-status');

        Route::get('/landlord/billing/plans', [BillingController::class, 'showPlans'])->name('landlord.billing.plans');
        Route::post('/landlord/billing/plans', [BillingController::class, 'storePlan'])->name('landlord.billing.plans.store');
        Route::post('/landlord/billing/plans/{plan}', [BillingController::class, 'updatePlan'])->name('landlord.billing.plans.update');
        Route::post('/landlord/billing/plans/{plan}/toggle-status', [BillingController::class, 'togglePlanStatus'])->name('landlord.billing.plans.toggle-status');

        Route::get('/landlord/billing/subscriptions', [BillingController::class, 'showSubscriptions'])->name('landlord.billing.subscriptions');
        Route::post('/landlord/billing/subscriptions', [BillingController::class, 'storeSubscription'])->name('landlord.billing.subscriptions.store');
        Route::post('/landlord/billing/subscriptions/{subscription}/cancel', [BillingController::class, 'cancelSubscription'])->name('landlord.billing.subscriptions.cancel');
        Route::post('/landlord/billing/subscriptions/{subscription}/invoices', [BillingController::class, 'generateInvoice'])->name('landlord.billing.invoices.generate');

        Route::get('/landlord/billing/invoices', [BillingController::class, 'showInvoices'])->name('landlord.billing.invoices');
        Route::get('/landlord/billing/invoices/{invoice}', [BillingController::class, 'showInvoice'])->name('landlord.billing.invoices.show');
        Route::post('/landlord/billing/invoices/{invoice}/pay', [BillingController::class, 'recordPayment'])->name('landlord.billing.invoices.pay');
    });
});
