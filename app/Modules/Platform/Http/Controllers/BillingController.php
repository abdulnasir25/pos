<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Billing\Actions\CancelSubscription;
use App\Modules\Billing\Actions\CreatePlan;
use App\Modules\Billing\Actions\CreateSubscription;
use App\Modules\Billing\Actions\GenerateInvoiceForSubscription;
use App\Modules\Billing\Actions\RecordInvoicePayment;
use App\Modules\Billing\Enums\PlanStatus;
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
    public function showPlans(): Response
    {
        return Inertia::render('Landlord/Billing/Plans', [
            'plans' => $this->planRows(),
        ]);
    }

    public function showSubscriptions(): Response
    {
        return Inertia::render('Landlord/Billing/Subscriptions', [
            'tenants' => Tenant::orderBy('name')->get(['id', 'name']),
            // Retired plans can't be picked for a new subscription — only
            // active ones are offered here. The full plan list (retired
            // included) lives on the Plans page.
            'plans' => Plan::where('status', PlanStatus::Active)->orderBy('price')->get(['id', 'name']),
            'subscriptions' => $this->subscriptionRows(),
        ]);
    }

    public function showInvoices(): Response
    {
        return Inertia::render('Landlord/Billing/Invoices', [
            'invoices' => $this->invoiceRows(),
        ]);
    }

    private function planRows()
    {
        return Plan::orderBy('price')->get()->map(fn (Plan $plan) => [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'price' => (string) $plan->price,
            'billing_interval' => $plan->billing_interval->value,
            'status' => $plan->status->value,
        ]);
    }

    private function subscriptionRows()
    {
        return Subscription::with(['tenant:id,name', 'plan:id,name'])
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
    }

    private function invoiceRows()
    {
        return Invoice::with('tenant:id,name')
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

    /**
     * Only the display name is editable — price, slug, and billing
     * interval are locked in the moment a plan exists, the same
     * effective-dated convention CreateSubscription documents. An
     * invoice is generated from $subscription->plan->price at
     * generation time (see GenerateInvoiceForSubscription), so
     * changing a plan's price after tenants have subscribed would
     * silently change what every one of them is billed next, with no
     * notice.
     */
    public function updatePlan(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $plan->update($validated);

        return back()->with('success', 'Plan updated.');
    }

    public function togglePlanStatus(Plan $plan): RedirectResponse
    {
        $plan->update(['status' => $plan->status === PlanStatus::Active ? PlanStatus::Retired : PlanStatus::Active]);

        return back()->with('success', 'Plan status updated.');
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

    /**
     * A subscription is never edited — no changing its plan or dates
     * on an existing row (see CreateSubscription's docblock: that's a
     * cancel-then-recreate, matching the never-rewrite-history
     * convention used throughout this codebase). Cancelling is also
     * one-way here, unlike the active/inactive toggles elsewhere — a
     * cancelled subscription isn't "reactivated", the tenant is put on
     * a fresh one via the Start Subscription form.
     */
    public function cancelSubscription(Subscription $subscription): RedirectResponse
    {
        app(CancelSubscription::class)->handle($subscription);

        return back()->with('success', 'Subscription cancelled.');
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
