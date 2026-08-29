<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Billing\Actions\CreatePlan;
use App\Modules\Billing\Actions\CreateSubscription;
use App\Modules\Billing\Actions\GenerateInvoiceForSubscription;
use App\Modules\Billing\Actions\RecordInvoicePayment;
use App\Modules\Billing\Exceptions\DuplicatePlanSlugException;
use App\Modules\Billing\Exceptions\InvoiceAlreadyPaidException;
use App\Modules\Billing\Exceptions\TenantAlreadyHasActiveSubscriptionException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        $tenants = Tenant::orderBy('name')->get(['id', 'name', 'slug', 'status']);

        $plans = Plan::orderBy('price')->get()->map(fn (Plan $plan) => [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'price' => (string) $plan->price,
            'billing_interval' => $plan->billing_interval->value,
            'status' => $plan->status->value,
        ]);

        $subscriptions = Subscription::with(['tenant:id,name', 'plan:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Subscription $sub) => [
                'id' => $sub->id,
                'tenant' => $sub->tenant->name,
                'tenant_id' => $sub->tenant_id,
                'plan' => $sub->plan->name,
                'status' => $sub->status->value,
                'current_period_start' => $sub->current_period_start->toDateString(),
                'current_period_end' => $sub->current_period_end->toDateString(),
            ]);

        $invoices = Invoice::with('tenant:id,name')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'tenant' => $invoice->tenant->name,
                'amount' => (string) $invoice->amount,
                'status' => $invoice->status->value,
                'due_date' => $invoice->due_date->toDateString(),
                'paid_at' => $invoice->paid_at?->toDateString(),
            ]);

        return Inertia::render('Landlord/Billing/Index', [
            'tenants' => $tenants,
            'plans' => $plans,
            'subscriptions' => $subscriptions,
            'invoices' => $invoices,
        ]);
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_interval' => ['required', 'in:monthly,yearly'],
        ]);

        try {
            app(CreatePlan::class)->handle(
                $validated['name'],
                $validated['slug'],
                (string) $validated['price'],
                $validated['billing_interval'],
            );
        } catch (DuplicatePlanSlugException $e) {
            return back()->withErrors(['plan' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Plan created.');
    }

    public function storeSubscription(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'start_date' => ['required', 'date'],
        ]);

        try {
            app(CreateSubscription::class)->handle(
                Tenant::findOrFail($validated['tenant_id']),
                Plan::findOrFail($validated['plan_id']),
                $validated['start_date'],
            );
        } catch (TenantAlreadyHasActiveSubscriptionException $e) {
            return back()->withErrors(['subscription' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Subscription started.');
    }

    public function generateInvoice(Subscription $subscription): RedirectResponse
    {
        app(GenerateInvoiceForSubscription::class)->handle($subscription);

        return back()->with('success', 'Invoice generated.');
    }

    public function recordPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'paid_at' => ['required', 'date'],
        ]);

        try {
            app(RecordInvoicePayment::class)->handle($invoice, $validated['paid_at']);
        } catch (InvoiceAlreadyPaidException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('success', 'Payment recorded.');
    }
}
