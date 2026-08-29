<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\FinancialPeriods\Actions\CreateFinancialPeriod;
use App\Modules\Inventory\Actions\RecordOpeningStock;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\ProfitCalculation\Actions\CalculateProfitForPeriod;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Sales\Actions\ConfirmSale;
use App\Modules\Sales\DTOs\CartLine;
use App\Modules\Sales\DTOs\PaymentAllocation;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReportsControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private string $baseUrl;
    private Tenant $tenant;
    private User $managerUser;
    private Unit $meter;
    private Warehouse $warehouse;
    private Product $product;
    private PaymentMethod $cash;
    private string $today;

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

        $this->meter = Unit::create(['name' => 'Meter']);
        $this->warehouse = Warehouse::create(['name' => 'Main Store']);
        $this->product = Product::create(['base_unit_id' => $this->meter->id, 'name' => 'Cotton', 'status' => 'active']);
        $this->cash = PaymentMethod::create(['name' => 'Cash']);
        $this->today = Carbon::today()->toDateString();

        app(RecordOpeningStock::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '1000.0000', '20.0000');

        $this->managerUser = User::create(['name' => 'Manager', 'email' => 'manager@alfateh.test', 'password' => bcrypt('secret')]);
        $managerRole = Role::where('slug', 'manager')->first();
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'reports.view')->first());
        app(AssignRoleToUser::class)->handle($this->managerUser, $managerRole);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    private function login(): void
    {
        $this->post("{$this->baseUrl}/login", ['email' => 'manager@alfateh.test', 'password' => 'secret']);
    }

    public function test_a_user_without_reports_view_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->get("{$this->baseUrl}/reports");

        $response->assertForbidden();
    }

    public function test_the_reports_page_defaults_to_this_month_and_shows_sales_summary(): void
    {
        app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->managerUser->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.00')],
            payments: [new PaymentAllocation($this->cash->id, '500.00')],
        );
        $this->login();

        $response = $this->get("{$this->baseUrl}/reports");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->where('salesSummary.revenue', '500.00')
            ->where('salesSummary.grossProfit', '300.00')
        );
    }

    public function test_stock_level_report_reflects_current_stock(): void
    {
        $this->login();

        $response = $this->get("{$this->baseUrl}/reports");

        $response->assertInertia(fn ($page) => $page
            ->where('stockLevel.rows.0.product', 'Cotton')
            ->where('stockLevel.rows.0.quantity', '1000.0000')
        );
    }

    public function test_stock_level_report_can_be_filtered_by_warehouse(): void
    {
        $secondWarehouse = Warehouse::create(['name' => 'Branch Store']);
        $this->login();

        $response = $this->get("{$this->baseUrl}/reports?warehouse_id={$secondWarehouse->id}");

        $response->assertInertia(fn ($page) => $page->has('stockLevel.rows', 0));
    }

    public function test_profit_and_loss_is_null_until_a_period_is_selected(): void
    {
        $this->login();

        $response = $this->get("{$this->baseUrl}/reports");

        $response->assertInertia(fn ($page) => $page->where('profitAndLoss', null));
    }

    public function test_profit_and_loss_shows_a_friendly_message_when_not_yet_calculated(): void
    {
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        $this->login();

        $response = $this->get("{$this->baseUrl}/reports?financial_period_id={$period->id}");

        $response->assertInertia(fn ($page) => $page
            ->where('profitAndLoss', null)
            ->has('profitAndLossError')
        );
        $this->assertStringContainsString('CalculateProfitForPeriod', $response->viewData('page')['props']['profitAndLossError']);
    }

    public function test_profit_and_loss_shows_the_calculation_once_it_exists(): void
    {
        app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->managerUser->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.00')],
            payments: [new PaymentAllocation($this->cash->id, '500.00')],
        );
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        app(CalculateProfitForPeriod::class)->handle($period, '100.00');
        $this->login();

        $response = $this->get("{$this->baseUrl}/reports?financial_period_id={$period->id}");

        $response->assertInertia(fn ($page) => $page
            ->where('profitAndLoss.netProfit', '300.00')
            ->where('profitAndLoss.distributableProfit', '100.00')
        );
    }

    public function test_outstanding_balances_lists_only_customers_and_suppliers_who_owe_or_are_owed(): void
    {
        \App\Modules\Customers\Models\Customer::create(['name' => 'Owing Customer', 'balance' => '1500.00', 'status' => 'active']);
        \App\Modules\Customers\Models\Customer::create(['name' => 'Settled Customer', 'balance' => '0.00', 'status' => 'active']);
        $this->login();

        $response = $this->get("{$this->baseUrl}/reports");

        $response->assertInertia(fn ($page) => $page
            ->has('outstandingBalances.customers', 1)
            ->where('outstandingBalances.totalReceivable', '1500.00')
        );
    }
}
