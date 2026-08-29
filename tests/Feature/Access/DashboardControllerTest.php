<?php

namespace Tests\Feature\Access;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Inventory\Actions\RecordOpeningStock;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Sales\Actions\ConfirmSale;
use App\Modules\Sales\DTOs\CartLine;
use App\Modules\Sales\DTOs\PaymentAllocation;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private string $baseUrl;
    private Tenant $tenant;

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
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    private function login(string $email, string $password = 'secret'): void
    {
        $this->post("{$this->baseUrl}/login", ['email' => $email, 'password' => $password]);
    }

    public function test_a_user_without_reports_view_sees_a_dashboard_with_no_financial_data(): void
    {
        $user = User::create(['name' => 'Cashier', 'email' => 'cashier@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($user)->get("{$this->baseUrl}/dashboard");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->missing('salesSummary')
        );
    }

    public function test_a_user_with_reports_view_sees_sales_summary_and_stock_value(): void
    {
        $user = User::create(['name' => 'Manager', 'email' => 'manager@alfateh.test', 'password' => bcrypt('secret')]);
        $managerRole = Role::where('slug', 'manager')->first();
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'reports.view')->first());
        app(AssignRoleToUser::class)->handle($user, $managerRole);

        // Real data flowing through: confirm an actual sale so the
        // dashboard's revenue/COGS/gross-profit numbers reflect
        // something other than zeros.
        $meter = Unit::create(['name' => 'Meter']);
        $warehouse = Warehouse::create(['name' => 'Main Store']);
        $product = Product::create(['base_unit_id' => $meter->id, 'name' => 'Cotton', 'status' => 'active']);
        app(RecordOpeningStock::class)->handle($product, $warehouse->id, $meter->id, '100', '10.00');
        $cash = PaymentMethod::create(['name' => 'Cash']);

        app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $warehouse->id,
            cashierId: $user->id,
            salesEmployeeId: null,
            lines: [new CartLine($product, $meter->id, '5', '20.00', '0.00')],
            payments: [new PaymentAllocation($cash->id, '100.00')],
        );

        $this->actingAs($user);
        $response = $this->get("{$this->baseUrl}/dashboard");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('salesSummary.saleCount', 1)
            ->where('salesSummary.revenue', '100.00')
            ->has('stockLevel')
            ->has('outstandingBalances')
        );
    }
}
