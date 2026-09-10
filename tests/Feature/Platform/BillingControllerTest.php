<?php

namespace Tests\Feature\Platform;

use App\Modules\Billing\Actions\CreatePlan;
use App\Modules\Billing\Actions\CreateSubscription;
use App\Modules\Billing\Actions\GenerateInvoiceForSubscription;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Platform\Models\LandlordUser;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Purely landlord-side, same isolation as BillingTest — no tenant
 * database is ever created here. Covers the auth boundary (guest vs
 * the 'landlord' guard, never the tenant 'web' guard) plus the thin
 * controller wrapping the already-tested Billing actions.
 */
class BillingControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = storage_path('framework/testing/tenants-'.uniqid());
        config(['tenancy.tenant_database_path' => $this->tenantDbPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);

        parent::tearDown();
    }

    private function admin(): LandlordUser
    {
        return LandlordUser::create(['name' => 'Owner', 'email' => 'owner@platform.test', 'password' => 'secret']);
    }

    private function tenant(): Tenant
    {
        return Tenant::create(['name' => 'Al-Fateh Cloth House', 'slug' => 'alfateh', 'database' => 'alfateh', 'status' => 'active']);
    }

    public function test_a_guest_is_redirected_to_the_landlord_login_page(): void
    {
        $response = $this->get('/landlord/billing');

        $response->assertRedirect('/landlord/login');
    }

    public function test_a_landlord_admin_can_log_in_and_view_the_billing_page(): void
    {
        $this->admin();

        $login = $this->post('/landlord/login', ['email' => 'owner@platform.test', 'password' => 'secret']);
        $login->assertRedirect('/landlord/billing');

        $response = $this->get('/landlord/billing');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Landlord/Billing/Index'));
    }

    public function test_an_already_authenticated_admin_visiting_login_is_sent_to_billing_not_dashboard(): void
    {
        $this->actingAs($this->admin(), 'landlord');

        $response = $this->get('/landlord/login');

        $response->assertRedirect('/landlord/billing');
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $this->admin();

        $response = $this->post('/landlord/login', ['email' => 'owner@platform.test', 'password' => 'wrong']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('landlord');
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

    public function test_a_shop_can_be_provisioned_through_the_form(): void
    {
        $this->actingAs($this->admin(), 'landlord');

        $response = $this->post('/landlord/tenants', [
            'name' => 'Al-Fateh Cloth House',
            'slug' => 'alfateh',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@alfateh.test',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame('active', Tenant::where('slug', 'alfateh')->value('status'));
        $this->assertFileExists($this->tenantDbPath.'/alfateh.sqlite');
    }

    public function test_provisioning_without_an_owner_email_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'landlord');

        $response = $this->post('/landlord/tenants', [
            'name' => 'Al-Fateh Cloth House',
            'owner_name' => 'Owner',
        ]);

        $response->assertSessionHasErrors('owner_email');
        $this->assertSame(0, Tenant::count());
    }

    public function test_a_duplicate_shop_subdomain_shows_an_error_not_a_crash(): void
    {
        $this->actingAs($this->admin(), 'landlord');
        $this->tenant();

        $response = $this->post('/landlord/tenants', [
            'name' => 'Al-Fateh Again',
            'slug' => 'alfateh',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@alfateh.test',
        ]);

        $response->assertSessionHasErrors('tenant');
    }

    public function test_a_shops_status_can_be_toggled_through_the_form(): void
    {
        $this->actingAs($this->admin(), 'landlord');
        $tenant = $this->tenant();

        $this->post("/landlord/tenants/{$tenant->id}/toggle-status")->assertRedirect();
        $this->assertSame('suspended', $tenant->fresh()->status);
        $this->assertNotNull($tenant->fresh()->suspended_at);

        $this->post("/landlord/tenants/{$tenant->id}/toggle-status")->assertRedirect();
        $this->assertSame('active', $tenant->fresh()->status);
        $this->assertNull($tenant->fresh()->suspended_at);
    }

    public function test_a_guest_cannot_provision_a_shop(): void
    {
        $response = $this->post('/landlord/tenants', ['name' => 'Al-Fateh Cloth House']);

        $response->assertRedirect('/landlord/login');
        $this->assertSame(0, Tenant::count());
    }
}
