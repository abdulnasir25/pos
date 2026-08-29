<?php

namespace Tests\Feature\Commission;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Commission\Actions\ApproveCommissionEntry;
use App\Modules\Commission\Actions\CalculateCommissionForPeriod;
use App\Modules\Commission\Actions\CreateCommissionRule;
use App\Modules\Commission\Actions\FinalizeCommissionEntry;
use App\Modules\Commission\Models\CommissionEntry;
use App\Modules\Commission\Models\CommissionRule;
use App\Modules\Employees\Actions\CreateEmployee;
use App\Modules\FinancialPeriods\Actions\CreateFinancialPeriod;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use App\Modules\Inventory\Actions\RecordOpeningStock;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Platform\Models\Tenant;
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

class CommissionControllerTest extends TestCase
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
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'commission.manage')->first());
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

    private function confirmSale(string $quantity, string $unitPrice): void
    {
        app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->managerUser->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, $quantity, $unitPrice)],
            payments: [new PaymentAllocation($this->cash->id, bcmul($quantity, $unitPrice, 2))],
        );
    }

    public function test_a_user_without_commission_manage_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->get("{$this->baseUrl}/commission");

        $response->assertForbidden();
    }

    public function test_a_commission_rule_can_be_added_through_the_form(): void
    {
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        $this->login();

        $response = $this->post("{$this->baseUrl}/commission/rules", [
            'employee_id' => $employee->id,
            'rate' => 10,
            'effective_from' => $this->today,
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, CommissionRule::where('employee_id', $employee->id)->count());
    }

    public function test_a_financial_period_can_be_created_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/commission/periods", [
            'period_start' => $this->today,
            'period_end' => $this->today,
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, FinancialPeriod::count());
    }

    public function test_an_overlapping_period_shows_an_error_not_a_crash(): void
    {
        app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        $this->login();

        $response = $this->post("{$this->baseUrl}/commission/periods", [
            'period_start' => $this->today,
            'period_end' => $this->today,
        ]);

        $response->assertSessionHasErrors('period');
    }

    public function test_calculating_commission_through_the_form_creates_entries(): void
    {
        $this->confirmSale('10.0000', '50.00');
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(CreateCommissionRule::class)->handle($employee, '10.00', $this->today);
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        $this->login();

        $response = $this->post("{$this->baseUrl}/commission/calculate", ['financial_period_id' => $period->id]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, CommissionEntry::where('financial_period_id', $period->id)->count());
    }

    public function test_calculating_twice_shows_an_error_not_a_crash(): void
    {
        $this->confirmSale('10.0000', '50.00');
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(CreateCommissionRule::class)->handle($employee, '10.00', $this->today);
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        app(CalculateCommissionForPeriod::class)->handle($period);
        $this->login();

        $response = $this->post("{$this->baseUrl}/commission/calculate", ['financial_period_id' => $period->id]);

        $response->assertSessionHasErrors('calculate');
    }

    public function test_the_full_lifecycle_can_be_driven_through_the_buttons(): void
    {
        $this->confirmSale('10.0000', '50.00');
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(CreateCommissionRule::class)->handle($employee, '10.00', $this->today);
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        $entry = app(CalculateCommissionForPeriod::class)->handle($period)->first();
        $this->login();

        $this->post("{$this->baseUrl}/commission/entries/{$entry->id}/approve")->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('approved', $entry->fresh()->status->value);

        $this->post("{$this->baseUrl}/commission/entries/{$entry->id}/finalize")->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('finalized', $entry->fresh()->status->value);

        $this->post("{$this->baseUrl}/commission/entries/{$entry->id}/pay")->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('paid', $entry->fresh()->status->value);
    }

    public function test_finalizing_before_approval_shows_an_error_not_a_crash(): void
    {
        $this->confirmSale('10.0000', '50.00');
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(CreateCommissionRule::class)->handle($employee, '10.00', $this->today);
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        $entry = app(CalculateCommissionForPeriod::class)->handle($period)->first();
        $this->login();

        $response = $this->post("{$this->baseUrl}/commission/entries/{$entry->id}/finalize");

        $response->assertSessionHasErrors('entry');
    }

    public function test_a_correction_can_be_recorded_through_the_form(): void
    {
        $this->confirmSale('10.0000', '50.00');
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(CreateCommissionRule::class)->handle($employee, '10.00', $this->today);
        $closedPeriod = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        $entry = app(CalculateCommissionForPeriod::class)->handle($closedPeriod)->first();
        app(ApproveCommissionEntry::class)->handle($entry, $this->managerUser->id);
        app(FinalizeCommissionEntry::class)->handle($entry);
        $tomorrow = Carbon::tomorrow()->toDateString();
        $openPeriod = app(CreateFinancialPeriod::class)->handle($tomorrow, $tomorrow);
        $this->login();

        $response = $this->post("{$this->baseUrl}/commission/corrections", [
            'original_commission_entry_id' => $entry->id,
            'financial_period_id' => $openPeriod->id,
            'amount' => -10,
            'reason' => 'sale_return',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, \App\Modules\Commission\Models\CommissionCorrection::count());
    }
}
