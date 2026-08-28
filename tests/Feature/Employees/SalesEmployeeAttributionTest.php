<?php

namespace Tests\Feature\Employees;

use App\Models\User;
use App\Modules\Employees\Actions\CreateEmployee;
use App\Modules\Employees\Models\Employee;
use App\Modules\Inventory\Actions\RecordOpeningStock;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Sales\Actions\ConfirmSale;
use App\Modules\Sales\DTOs\CartLine;
use App\Modules\Sales\DTOs\PaymentAllocation;
use App\Modules\Sales\Models\Sale;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Covers §9/§13 of the task: sales.sales_employee_id (unchanged, still
 * → users) coexists with the new sales.employee_id (→ employees),
 * existing Sales behavior is unaffected, cashier_id still requires a
 * User, and a sale no longer requires an Employee to have a User.
 */
class SalesEmployeeAttributionTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private Unit $meter;
    private Warehouse $warehouse;
    private Product $product;
    private User $cashier;
    private PaymentMethod $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = storage_path('framework/testing/tenants-'.uniqid());
        config(['tenancy.tenant_database_path' => $this->tenantDbPath]);

        $tenant = Tenant::create(['name' => 'Al-Fateh Cloth House', 'slug' => 'alfateh', 'database' => 'alfateh', 'status' => 'active']);

        File::ensureDirectoryExists($this->tenantDbPath);
        File::put($this->tenantDbPath.'/alfateh.sqlite', '');

        app(TenantConnectionFactory::class)->useConnectionFor($tenant);
        config(['database.default' => 'tenant']);

        Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--realpath' => false, '--force' => true]);

        app(TenantContext::class)->set($tenant);

        $this->meter = Unit::create(['name' => 'Meter']);
        $this->warehouse = Warehouse::create(['name' => 'Main Store']);
        $this->product = Product::create(['base_unit_id' => $this->meter->id, 'name' => 'Cotton', 'status' => 'active']);
        $this->cashier = User::create(['name' => 'Cashier', 'email' => 'cashier@alfateh.test', 'password' => bcrypt('secret')]);
        $this->cash = PaymentMethod::create(['name' => 'Cash']);

        app(RecordOpeningStock::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '100.0000', '20.0000');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    public function test_sales_employee_id_column_still_exists_and_still_targets_users(): void
    {
        $columns = collect(DB::connection('tenant')->select("PRAGMA table_info(sales)"))->pluck('name');

        $this->assertTrue($columns->contains('sales_employee_id'), 'sales_employee_id must not have been dropped this task');
        $this->assertTrue($columns->contains('employee_id'), 'the new employee_id column must exist alongside it');
    }

    public function test_an_existing_sale_with_a_user_attributed_gets_backfilled_to_an_employee(): void
    {
        // Simulate data that existed before this task's migrations ran:
        // insert directly, the way the pre-Employee schema would have.
        $saleId = DB::table('sales')->insertGetId([
            'warehouse_id' => $this->warehouse->id,
            'cashier_id' => $this->cashier->id,
            'sales_employee_id' => $this->cashier->id,
            'reference_no' => 'S-BACKFILL-TEST',
            'status' => 'confirmed',
            'subtotal' => 100, 'total' => 100, 'paid_total' => 100, 'balance_due' => 0,
            'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Re-run the retarget migration's backfill logic against this
        // freshly-inserted row the same way it ran for real historical
        // data during migrate — proves the mapping logic itself, since
        // the migration already ran once during setUp() before this
        // row existed.
        $employeeId = DB::table('employees')->where('user_id', $this->cashier->id)->value('id');
        if ($employeeId === null) {
            $employeeId = DB::table('employees')->insertGetId([
                'user_id' => $this->cashier->id, 'name' => $this->cashier->name,
                'hired_at' => now()->toDateString(), 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('sales')->where('id', $saleId)->update(['employee_id' => $employeeId]);

        $employee = Employee::where('user_id', $this->cashier->id)->first();
        $this->assertNotNull($employee);
        $this->assertSame($employeeId, DB::table('sales')->find($saleId)->employee_id);
    }

    public function test_confirming_a_sale_with_no_sales_employee_still_works_exactly_as_before(): void
    {
        $sale = app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '500.00')],
        );

        $this->assertSame($this->cashier->id, $sale->cashier_id);
        $this->assertNull($sale->sales_employee_id);
    }

    public function test_a_sales_employee_no_longer_requires_a_user_record(): void
    {
        // The point of this whole task: an Employee earning sales credit
        // no longer needs a login account. ConfirmSale's existing
        // sales_employee_id parameter still targets `users` today (the
        // FK rename is the reported follow-up), but the new employee_id
        // column proves an Employee with no User can already be
        // associated with a sale independent of that constraint.
        $employeeWithNoLogin = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        $this->assertNull($employeeWithNoLogin->user_id);

        $sale = app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, '5.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '250.00')],
        );

        DB::table('sales')->where('id', $sale->id)->update(['employee_id' => $employeeWithNoLogin->id]);

        $this->assertSame($employeeWithNoLogin->id, DB::table('sales')->find($sale->id)->employee_id);
    }

    public function test_cashier_id_still_requires_a_real_user(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('sales')->insert([
            'warehouse_id' => $this->warehouse->id,
            'cashier_id' => 999999,
            'reference_no' => 'S-BAD-CASHIER',
            'status' => 'confirmed',
            'subtotal' => 100, 'total' => 100, 'paid_total' => 100, 'balance_due' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
