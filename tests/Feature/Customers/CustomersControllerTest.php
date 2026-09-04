<?php

namespace Tests\Feature\Customers;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Customers\Models\Customer;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CustomersControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private string $baseUrl;
    private Tenant $tenant;
    private User $managerUser;
    private PaymentMethod $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = storage_path('framework/testing/tenants-'.uniqid());
        config(['tenancy.tenant_database_path' => $this->tenantDbPath]);

        $this->tenant = Tenant::create(['name' => 'Al-Fateh Cloth House', 'slug' => 'alfateh', 'database' => 'alfateh', 'status' => 'active']);
        $this->baseUrl = 'http://alfateh.pos.test';

        File::ensureDirectoryExists($this->tenantDbPath);
        File::put($this->tenantDbPath.'/alfateh.sqlite', '');

        app(TenantConnectionFactory::class)->useConnectionFor($this->tenant);
        config(['database.default' => 'tenant']);

        Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--realpath' => false, '--force' => true]);

        app(TenantContext::class)->set($this->tenant);

        $this->cash = PaymentMethod::create(['name' => 'Cash', 'status' => 'active']);

        $this->managerUser = User::create(['name' => 'Manager', 'email' => 'manager@alfateh.test', 'password' => bcrypt('secret')]);
        $managerRole = Role::where('slug', 'manager')->first();
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'customers.manage')->first());
        app(AssignRoleToUser::class)->handle($this->managerUser, $managerRole);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    private function resumeTenantContext(): void
    {
        app(TenantContext::class)->set($this->tenant);
        config(['database.default' => 'tenant']);
    }

    private function login(): void
    {
        $this->post("{$this->baseUrl}/login", ['email' => 'manager@alfateh.test', 'password' => 'secret']);
    }

    public function test_the_customers_manage_permission_exists_in_the_baseline_seed(): void
    {
        $this->assertNotNull(Permission::where('slug', 'customers.manage')->first());
    }

    public function test_a_user_without_customers_manage_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->get("{$this->baseUrl}/customers");

        $response->assertForbidden();
    }

    public function test_the_page_lists_customers_and_payment_methods(): void
    {
        Customer::create(['name' => 'Ahmed', 'balance' => '0.00', 'status' => 'active']);
        $this->login();

        $response = $this->get("{$this->baseUrl}/customers");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Customers/Index')
            ->has('customers', 1)
            ->has('paymentMethods', 1)
        );
    }

    public function test_a_customer_can_be_added_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/customers", ['name' => 'Ahmed', 'phone' => '0300-1234567']);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $customer = Customer::where('name', 'Ahmed')->first();
        $this->assertSame('0.00', $customer->balance);
        $this->assertSame('active', $customer->status);
    }

    public function test_a_payment_can_be_recorded_through_the_form(): void
    {
        $customer = Customer::create(['name' => 'Ahmed', 'balance' => '500.00', 'status' => 'active']);
        $this->login();

        $response = $this->post("{$this->baseUrl}/customers/{$customer->id}/payments", [
            'amount' => 200,
            'payment_method_id' => $this->cash->id,
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('300.00', $customer->fresh()->balance);
    }

    public function test_a_customer_can_be_updated_through_the_form(): void
    {
        $customer = Customer::create(['name' => 'Ahmed', 'balance' => '0.00', 'status' => 'active']);
        $this->login();

        $response = $this->post("{$this->baseUrl}/customers/{$customer->id}", [
            'name' => 'Ahmed Khan',
            'phone' => '0300-9999999',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $fresh = $customer->fresh();
        $this->assertSame('Ahmed Khan', $fresh->name);
        $this->assertSame('0300-9999999', $fresh->phone);
    }

    public function test_a_customer_can_be_deactivated_and_reactivated(): void
    {
        $customer = Customer::create(['name' => 'Ahmed', 'balance' => '0.00', 'status' => 'active']);
        $this->login();

        $this->post("{$this->baseUrl}/customers/{$customer->id}/toggle-status")->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('inactive', $customer->fresh()->status);

        $this->login();
        $this->post("{$this->baseUrl}/customers/{$customer->id}/toggle-status")->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('active', $customer->fresh()->status);
    }
}
