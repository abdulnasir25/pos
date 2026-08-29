<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Actions\CancelSubscription;
use App\Modules\Billing\Actions\CreatePlan;
use App\Modules\Billing\Actions\CreateSubscription;
use App\Modules\Billing\Actions\GenerateInvoiceForSubscription;
use App\Modules\Billing\Actions\MarkOverdueInvoicesAndSuspendTenants;
use App\Modules\Billing\Actions\RecordInvoicePayment;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exceptions\DuplicatePlanSlugException;
use App\Modules\Billing\Exceptions\InvoiceAlreadyPaidException;
use App\Modules\Billing\Exceptions\TenantAlreadyHasActiveSubscriptionException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Purely landlord-side — no tenant database is ever created here.
 * Billing never touches a tenant's own data, only the shared landlord
 * tables (tenants, plans, subscriptions, invoices).
 */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug = 'alfateh'): Tenant
    {
        return Tenant::create(['name' => 'Al-Fateh Cloth House', 'slug' => $slug, 'database' => $slug, 'status' => 'active']);
    }

    // --- Plans ------------------------------------------------------------

    public function test_a_plan_can_be_created(): void
    {
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');

        $this->assertSame('Starter', $plan->name);
        $this->assertSame('2000.00', $plan->price);
    }

    public function test_a_duplicate_plan_slug_is_refused(): void
    {
        app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');

        $this->expectException(DuplicatePlanSlugException::class);

        app(CreatePlan::class)->handle('Starter Again', 'starter', '2000.00', 'monthly');
    }

    // --- Subscriptions ---------------------------------------------------

    public function test_a_monthly_subscription_computes_a_one_month_period(): void
    {
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');

        $subscription = app(CreateSubscription::class)->handle($tenant, $plan, '2026-01-01');

        $this->assertSame('2026-01-01', $subscription->current_period_start->toDateString());
        $this->assertSame('2026-01-31', $subscription->current_period_end->toDateString());
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
    }

    public function test_a_yearly_subscription_computes_a_one_year_period(): void
    {
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Pro Yearly', 'pro-yearly', '20000.00', 'yearly');

        $subscription = app(CreateSubscription::class)->handle($tenant, $plan, '2026-01-01');

        $this->assertSame('2026-12-31', $subscription->current_period_end->toDateString());
    }

    public function test_a_tenant_cannot_hold_two_active_subscriptions(): void
    {
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');
        app(CreateSubscription::class)->handle($tenant, $plan, '2026-01-01');

        $this->expectException(TenantAlreadyHasActiveSubscriptionException::class);

        app(CreateSubscription::class)->handle($tenant, $plan, '2026-01-01');
    }

    public function test_cancelling_a_subscription_allows_a_new_one_to_start(): void
    {
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');
        $first = app(CreateSubscription::class)->handle($tenant, $plan, '2026-01-01');

        app(CancelSubscription::class)->handle($first);
        $this->assertSame(SubscriptionStatus::Cancelled, $first->fresh()->status);

        $second = app(CreateSubscription::class)->handle($tenant, $plan, '2026-02-01');
        $this->assertSame(SubscriptionStatus::Active, $second->status);
    }

    // --- Invoices ------------------------------------------------------------

    public function test_an_invoice_is_generated_for_the_subscriptions_current_period(): void
    {
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');
        $subscription = app(CreateSubscription::class)->handle($tenant, $plan, '2026-01-01');

        $invoice = app(GenerateInvoiceForSubscription::class)->handle($subscription);

        $this->assertSame('2000.00', $invoice->amount);
        $this->assertSame(InvoiceStatus::Pending, $invoice->status);
        $this->assertSame('2026-01-01', $invoice->due_date->toDateString());
    }

    public function test_recording_payment_marks_the_invoice_paid(): void
    {
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');
        $subscription = app(CreateSubscription::class)->handle($tenant, $plan, '2026-01-01');
        $invoice = app(GenerateInvoiceForSubscription::class)->handle($subscription);

        app(RecordInvoicePayment::class)->handle($invoice, '2026-01-02');

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    public function test_paying_an_already_paid_invoice_is_refused(): void
    {
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');
        $subscription = app(CreateSubscription::class)->handle($tenant, $plan, '2026-01-01');
        $invoice = app(GenerateInvoiceForSubscription::class)->handle($subscription);
        app(RecordInvoicePayment::class)->handle($invoice, '2026-01-02');

        $this->expectException(InvoiceAlreadyPaidException::class);

        app(RecordInvoicePayment::class)->handle($invoice->fresh(), '2026-01-03');
    }

    // --- Overdue + suspension ----------------------------------------------

    public function test_an_overdue_invoice_suspends_its_tenant(): void
    {
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');
        $subscription = app(CreateSubscription::class)->handle($tenant, $plan, '2026-01-01');

        // Backdate due_date directly — GenerateInvoiceForSubscription
        // always bills upfront (due today or later), so simulate time
        // having passed instead of waiting for it.
        $invoice = app(GenerateInvoiceForSubscription::class)->handle($subscription);
        Invoice::where('id', $invoice->id)->update(['due_date' => Carbon::yesterday()]);

        $overdue = app(MarkOverdueInvoicesAndSuspendTenants::class)->handle();

        $this->assertCount(1, $overdue);
        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
        $this->assertTrue($tenant->fresh()->isSuspended());
    }

    public function test_a_tenant_with_multiple_overdue_invoices_is_only_suspended_once(): void
    {
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');
        $subscription = app(CreateSubscription::class)->handle($tenant, $plan, '2026-01-01');

        $invoiceOne = app(GenerateInvoiceForSubscription::class)->handle($subscription);
        Invoice::where('id', $invoiceOne->id)->update(['due_date' => Carbon::yesterday()]);
        $invoiceTwo = Invoice::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'amount' => '2000.00',
            'status' => InvoiceStatus::Pending,
            'period_start' => '2025-12-01',
            'period_end' => '2025-12-31',
            'due_date' => Carbon::yesterday()->subMonth(),
        ]);

        $overdue = app(MarkOverdueInvoicesAndSuspendTenants::class)->handle();

        $this->assertCount(2, $overdue);
        $this->assertSame('suspended', $tenant->fresh()->status);
    }

    public function test_paying_an_overdue_invoice_reactivates_the_suspended_tenant(): void
    {
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');
        $subscription = app(CreateSubscription::class)->handle($tenant, $plan, '2026-01-01');
        $invoice = app(GenerateInvoiceForSubscription::class)->handle($subscription);
        Invoice::where('id', $invoice->id)->update(['due_date' => Carbon::yesterday()]);
        app(MarkOverdueInvoicesAndSuspendTenants::class)->handle();
        $this->assertTrue($tenant->fresh()->isSuspended());

        app(RecordInvoicePayment::class)->handle($invoice->fresh(), now()->toDateString());

        $this->assertTrue($tenant->fresh()->isActive());
        $this->assertNull($tenant->fresh()->suspended_at);
    }

    public function test_invoices_not_yet_due_are_left_alone(): void
    {
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');
        $subscription = app(CreateSubscription::class)->handle($tenant, $plan, now()->addMonth()->toDateString());
        app(GenerateInvoiceForSubscription::class)->handle($subscription);

        $overdue = app(MarkOverdueInvoicesAndSuspendTenants::class)->handle();

        $this->assertCount(0, $overdue);
        $this->assertFalse($tenant->fresh()->isSuspended());
    }
}
