<?php

use App\Modules\Platform\Http\Controllers\BillingController;
use App\Modules\Platform\Http\Controllers\LandlordLoginController;
use App\Modules\Platform\Http\Middleware\HandleLandlordInertiaRequests;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(HandleLandlordInertiaRequests::class)->group(function () {
    Route::get('/landlord/login', [LandlordLoginController::class, 'show'])->middleware('guest:landlord')->name('landlord.login');
    Route::post('/landlord/login', [LandlordLoginController::class, 'store'])->middleware('guest:landlord');
    Route::post('/landlord/logout', [LandlordLoginController::class, 'destroy'])->middleware('auth:landlord');

    Route::middleware('auth:landlord')->group(function () {
        Route::get('/landlord/billing', [BillingController::class, 'show'])->name('landlord.billing');
        Route::post('/landlord/billing/plans', [BillingController::class, 'storePlan'])->name('landlord.billing.plans.store');
        Route::post('/landlord/billing/subscriptions', [BillingController::class, 'storeSubscription'])->name('landlord.billing.subscriptions.store');
        Route::post('/landlord/billing/subscriptions/{subscription}/invoices', [BillingController::class, 'generateInvoice'])->name('landlord.billing.invoices.generate');
        Route::post('/landlord/billing/invoices/{invoice}/pay', [BillingController::class, 'recordPayment'])->name('landlord.billing.invoices.pay');
    });
});
