<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Actions\RecordCustomerPayment;
use App\Modules\Customers\Models\Customer;
use App\Modules\Payments\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomersController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        return Inertia::render('Customers/Index', [
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'phone', 'balance', 'status']),
            'paymentMethods' => PaymentMethod::where('status', 'active')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        Customer::create([...$validated, 'balance' => '0.00', 'status' => 'active']);

        return back()->with('success', 'Customer added.');
    }

    public function storePayment(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
        ]);

        app(RecordCustomerPayment::class)->handle(
            $customer,
            (string) $validated['amount'],
            $validated['payment_method_id'],
            $request->user()->id,
        );

        return back()->with('success', 'Payment recorded.');
    }
}
