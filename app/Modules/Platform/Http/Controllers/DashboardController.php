<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Platform\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        $tenants = Tenant::all(['id', 'status']);

        // Yearly plans are normalized to a monthly figure so mixed
        // billing intervals still sum into one comparable MRR number.
        $mrr = Subscription::where('status', SubscriptionStatus::Active)
            ->with('plan:id,price,billing_interval')
            ->get()
            ->reduce(function (string $total, Subscription $sub) {
                $monthly = $sub->plan->billing_interval === BillingInterval::Yearly
                    ? bcdiv((string) $sub->plan->price, '12', 2)
                    : (string) $sub->plan->price;

                return bcadd($total, $monthly, 2);
            }, '0.00');

        // Invoices whose due date has passed without ever being marked
        // Overdue by the (currently unscheduled) suspension sweep are
        // counted here too — the dashboard shouldn't understate risk
        // just because that cron isn't wired up yet.
        $overdueInvoices = Invoice::with('tenant:id,name')
            ->where(function ($query) {
                $query->where('status', InvoiceStatus::Overdue)
                    ->orWhere(function ($query) {
                        $query->where('status', InvoiceStatus::Pending)
                            ->whereDate('due_date', '<', now()->toDateString());
                    });
            })
            ->orderBy('due_date')
            ->get();

        $paidThisMonth = Invoice::where('status', InvoiceStatus::Paid)
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $overdueAmount = $overdueInvoices->reduce(
            fn (string $total, Invoice $invoice) => bcadd($total, (string) $invoice->amount, 2),
            '0.00',
        );

        return Inertia::render('Landlord/Dashboard', [
            'stats' => [
                'tenants_total' => $tenants->count(),
                'tenants_active' => $tenants->where('status', 'active')->count(),
                'tenants_suspended' => $tenants->where('status', 'suspended')->count(),
                'mrr' => $mrr,
                'overdue_count' => $overdueInvoices->count(),
                'overdue_amount' => $overdueAmount,
                'collected_this_month' => number_format((float) $paidThisMonth, 2, '.', ''),
            ],
            'overdueInvoices' => $overdueInvoices->take(10)->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'tenant' => $invoice->tenant->name,
                'amount' => (string) $invoice->amount,
                'due_date' => $invoice->due_date->toDateString(),
            ]),
            'recentTenants' => Tenant::orderByDesc('id')->limit(5)->get(['id', 'name', 'slug', 'status'])
                ->map(fn (Tenant $tenant) => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'status' => $tenant->status,
                ]),
        ]);
    }
}
