<?php

namespace Tests\Feature\Platform;

use App\Modules\Billing\Actions\CreatePlan;
use App\Modules\Billing\Actions\CreateSubscription;
use App\Modules\Billing\Actions\GenerateInvoiceForSubscription;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Platform\Models\LandlordUser;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Purely landlord-side — no tenant database is ever created here.
 * Covers the Plans/Subscriptions/Invoices pages and the actions they
 * post to. Tenant provisioning and its page live in
 * TenantsControllerTest instead.
 */
class BillingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): LandlordUser
    {
        return LandlordUser::create(['name' => 'Owner', 'email' => 'owner@platform.test', 'password' => 'secret']);
    }

    private function tenant(): Tenant
    {
        return Tenant::create(['name' => 'Al-Fateh Cloth House', 'slug' => 'alfateh', 'database' => 'alfateh', 'status' => 'active']);
    }

    public function test_a_guest_cannot_view_any_billing_page(): void
    {
        $this->get('/landlord/billing/plans')->assertRedirect('/landlord/login');
        $this->get('/landlord/billing/subscriptions')->assertRedirect('/landlord/login');
        $this->get('/landlord/billing/invoices')->assertRedirect('/landlord/login');
    }

    public function test_a_landlord_admin_can_view_the_plans_page(): void
    {
        $this->actingAs($this->admin(), 'landlord');

        $response = $this->get('/landlord/billing/plans');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Landlord/Billing/Plans'));
    }

    public function test_a_landlord_admin_can_view_the_subscriptions_page(): void
    {
        $this->actingAs($this->admin(), 'landlord');

        $response = $this->get('/landlord/billing/subscriptions');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Landlord/Billing/Subscriptions'));
    }

    public function test_a_landlord_admin_can_view_the_invoices_page(): void
    {
        $this->actingAs($this->admin(), 'landlord');

        $response = $this->get('/landlord/billing/invoices');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Landlord/Billing/Invoices'));
    }

    public function test_a_plan_can_be_created_through_the_form(): void
    {
        $this->actingAs($this->admin(), 'landlord');

        $response = $this->post('/landlord/billing/plans', [
            'name' => 'Starter',
            'slug' => 'starter',
            'price' => 2000,
            'billing_interval' => 'monthly',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_a_duplicate_plan_slug_shows_an_error_not_a_crash(): void
    {
        $this->actingAs($this->admin(), 'landlord');
        app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');

        $response = $this->post('/landlord/billing/plans', [
            'name' => 'Starter Again',
            'slug' => 'starter',
            'price' => 2000,
            'billing_interval' => 'monthly',
        ]);

        $response->assertSessionHasErrors('plan');
    }

    public function test_a_plans_name_can_be_updated_through_the_form(): void
    {
        $this->actingAs($this->admin(), 'landlord');
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');

        $response = $this->post("/landlord/billing/plans/{$plan->id}", ['name' => 'Starter Plan']);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $fresh = $plan->fresh();
        $this->assertSame('Starter Plan', $fresh->name);
        // Untouched — price/slug/interval are not editable through this form.
        $this->assertSame('2000.00', $fresh->price);
        $this->assertSame('starter', $fresh->slug);
    }

    public function test_a_plans_status_can_be_toggled_through_the_form(): void
    {
        $this->actingAs($this->admin(), 'landlord');
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');

        $this->post("/landlord/billing/plans/{$plan->id}/toggle-status")->assertRedirect();
        $this->assertSame('retired', $plan->fresh()->status->value);

        $this->post("/landlord/billing/plans/{$plan->id}/toggle-status")->assertRedirect();
        $this->assertSame('active', $plan->fresh()->status->value);
    }

    public function test_the_subscriptions_page_only_offers_active_plans(): void
    {
        $this->actingAs($this->admin(), 'landlord');
        $active = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');
        $retired = app(CreatePlan::class)->handle('Legacy', 'legacy', '1000.00', 'monthly');
        $retired->update(['status' => 'retired']);

        $response = $this->get('/landlord/billing/subscriptions');

        $response->assertInertia(fn ($page) => $page
            ->has('plans', 1)
            ->where('plans.0.id', $active->id)
        );
    }

    public function test_a_subscription_can_be_started_through_the_form(): void
    {
        $this->actingAs($this->admin(), 'landlord');
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');

        $response = $this->post('/landlord/billing/subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'start_date' => '2026-01-01',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_an_invoice_can_be_generated_and_paid_through_the_form(): void
    {
        $this->actingAs($this->admin(), 'landlord');
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');
        $subscription = app(CreateSubscription::class)->handle($tenant, $plan, '2026-01-01');

        $generate = $this->post("/landlord/billing/subscriptions/{$subscription->id}/invoices");
        $generate->assertRedirect();

        $invoice = Invoice::firstOrFail();

        $pay = $this->post("/landlord/billing/invoices/{$invoice->id}/pay", ['paid_at' => '2026-01-02']);
        $pay->assertRedirect();
        $this->assertSame('paid', $invoice->fresh()->status->value);
    }

    public function test_paying_an_already_paid_invoice_shows_an_error_not_a_crash(): void
    {
        $this->actingAs($this->admin(), 'landlord');
        $tenant = $this->tenant();
        $plan = app(CreatePlan::class)->handle('Starter', 'starter', '2000.00', 'monthly');
        $subscription = app(CreateSubscription::class)->handle($tenant, $plan, '2026-01-01');
        $invoice = app(GenerateInvoiceForSubscription::class)->handle($subscription);
        app(\App\Modules\Billing\Actions\RecordInvoicePayment::class)->handle($invoice, '2026-01-02');

        $response = $this->post("/landlord/billing/invoices/{$invoice->id}/pay", ['paid_at' => '2026-01-03']);

        $response->assertSessionHasErrors('invoice');
    }
}
