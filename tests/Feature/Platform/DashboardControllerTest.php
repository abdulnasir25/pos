<?php

namespace Tests\Feature\Platform;

use App\Modules\Billing\Actions\CreatePlan;
use App\Modules\Billing\Actions\CreateSubscription;
use App\Modules\Billing\Actions\GenerateInvoiceForSubscription;
use App\Modules\Billing\Actions\RecordInvoicePayment;
use App\Modules\Platform\Models\LandlordUser;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Purely landlord-side — no tenant database is ever created here,
 * same isolation as BillingControllerTest. The dashboard's numbers are
 * computed straight from the landlord tables (tenants, subscriptions,
 * invoices), so a Tenant row is enough — no provisioning needed.
 */
class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): LandlordUser
    {
        return LandlordUser::create(['name' => 'Owner', 'email' => 'owner@platform.test', 'password' => 'secret']);
    }

    private function tenant(string $slug = 'alfateh'): Tenant
    {
        return Tenant::create(['name' => 'Al-Fateh Cloth House', 'slug' => $slug, 'database' => $slug, 'status' => 'active']);
    }

    public function test_a_guest_is_redirected_to_the_landlord_login_page(): void
    {
        $this->get('/landlord/dashboard')->assertRedirect('/landlord/login');
    }

    public function test_an_already_authenticated_admin_visiting_login_is_sent_to_the_dashboard(): void
    {
        $this->actingAs($this->admin(), 'landlord');

        $this->get('/landlord/login')->assertRedirect('/landlord/dashboard');
    }

    public function test_the_dashboard_counts_shops_by_status(): void
    {
        $this->actingAs($this->admin(), 'landlord');
        $this->tenant('alfateh');
        $this->tenant('karachi')->update(['status' => 'suspended']);

        $response = $this->get('/landlord/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Landlord/Dashboard')
            ->where('stats.tenants_total', 2)
            ->where('stats.tenants_active', 1)
            ->where('stats.tenants_suspended', 1)
        );
    }

    public function test_mrr_sums_active_subscriptions_normalizing_yearly_plans_to_monthly(): void
    {
        $this->actingAs($this->admin(), 'landlord');
        $tenantA = $this->tenant('alfateh');
        $tenantB = $this->tenant('karachi');
        $monthlyPlan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');
        $yearlyPlan = app(CreatePlan::class)->handle('Yearly', 'yearly', '12000.00', 'yearly');
        app(CreateSubscription::class)->handle($tenantA, $monthlyPlan, '2026-01-01');
        app(CreateSubscription::class)->handle($tenantB, $yearlyPlan, '2026-01-01');

        $response = $this->get('/landlord/dashboard');

        // 2000.00 (monthly) + 12000.00/12 = 1000.00 (yearly, normalized) = 3000.00
        $response->assertInertia(fn ($page) => $page->where('stats.mrr', '3000.00'));
    }

    public function test_cancelled_subscriptions_are_excluded_from_mrr(): void
    {
        $this->actingAs($this->admin(), 'landlord');
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');
        $subscription = app(CreateSubscription::class)->handle($tenant, $plan, '2026-01-01');
        $subscription->update(['status' => 'cancelled']);

        $response = $this->get('/landlord/dashboard');

        $response->assertInertia(fn ($page) => $page->where('stats.mrr', '0.00'));
    }

    public function test_a_pending_invoice_past_its_due_date_counts_as_overdue(): void
    {
        $this->actingAs($this->admin(), 'landlord');
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');
        $subscription = app(CreateSubscription::class)->handle($tenant, $plan, '2020-01-01');
        $invoice = app(GenerateInvoiceForSubscription::class)->handle($subscription);

        $response = $this->get('/landlord/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->where('stats.overdue_count', 1)
            ->where('stats.overdue_amount', '2000.00')
            ->has('overdueInvoices', 1)
            ->where('overdueInvoices.0.id', $invoice->id)
        );
    }

    public function test_a_paid_invoice_does_not_count_as_overdue(): void
    {
        $this->actingAs($this->admin(), 'landlord');
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');
        $subscription = app(CreateSubscription::class)->handle($tenant, $plan, '2020-01-01');
        $invoice = app(GenerateInvoiceForSubscription::class)->handle($subscription);
        app(RecordInvoicePayment::class)->handle($invoice, now()->toDateString());

        $response = $this->get('/landlord/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->where('stats.overdue_count', 0)
            ->where('stats.collected_this_month', '2000.00')
        );
    }

    public function test_the_dashboard_lists_recently_added_shops(): void
    {
        $this->actingAs($this->admin(), 'landlord');
        $this->tenant('alfateh');
        $this->tenant('karachi');

        $response = $this->get('/landlord/dashboard');

        $response->assertInertia(fn ($page) => $page->has('recentTenants', 2));
    }
}
