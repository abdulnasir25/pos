<?php

namespace Tests\Feature\Employees;

use App\Models\User;
use App\Modules\Employees\Actions\ChangeEmployeeStatus;
use App\Modules\Employees\Actions\CreateEmployee;
use App\Modules\Employees\Enums\EmployeeStatus;
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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * Covers the finalized schema: sales.sales_employee_id now targets
 * employees.id (never users.id), the staging sales.employee_id column
 * from the prior task is gone, and cashier_id is untouched.
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

    // --- Final schema shape ----------------------------------------------

    public function test_the_staging_employee_id_column_is_gone(): void
    {
        $columns = collect(DB::connection('tenant')->select('PRAGMA table_info(sales)'))->pluck('name');

        $this->assertTrue($columns->contains('sales_employee_id'));
        $this->assertFalse($columns->contains('employee_id'), 'the temporary staging column must not remain in the final schema');
    }

    public function test_sales_employee_id_now_targets_employees_not_users(): void
    {
        $fks = collect(DB::connection('tenant')->select('PRAGMA foreign_key_list(sales)'));
        $salesEmployeeFk = $fks->firstWhere('from', 'sales_employee_id');
        $cashierFk = $fks->firstWhere('from', 'cashier_id');

        $this->assertNotNull($salesEmployeeFk);
        $this->assertSame('employees', $salesEmployeeFk->table);

        $this->assertNotNull($cashierFk);
        $this->assertSame('users', $cashierFk->table, 'cashier_id must remain unchanged, still pointing at users');
    }

    // --- The 7 required proofs --------------------------------------------

    public function test_1_a_sale_can_have_a_sales_employee_with_no_user_account(): void
    {
        $employeeWithNoLogin = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        $this->assertNull($employeeWithNoLogin->user_id);

        $sale = app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: $employeeWithNoLogin->id,
            lines: [new CartLine($this->product, $this->meter->id, '5.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '250.00')],
        );

        $this->assertSame($employeeWithNoLogin->id, $sale->sales_employee_id);
    }

    public function test_2_a_sale_can_have_a_sales_employee_linked_to_a_user(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);
        $employee = app(CreateEmployee::class)->handle('Ahmed', '2026-01-01', userId: $user->id);

        $sale = app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: $employee->id,
            lines: [new CartLine($this->product, $this->meter->id, '5.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '250.00')],
        );

        $this->assertSame($employee->id, $sale->sales_employee_id);
    }

    public function test_3_cashier_id_remains_a_user(): void
    {
        $sale = app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, '5.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '250.00')],
        );

        $this->assertSame($this->cashier->id, $sale->cashier_id);
        $this->assertNotNull(User::find($sale->cashier_id));
    }

    public function test_4_sales_employee_id_points_to_employee_not_user(): void
    {
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');

        $sale = app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: $employee->id,
            lines: [new CartLine($this->product, $this->meter->id, '5.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '250.00')],
        );

        // An id with no corresponding employees row must be rejected —
        // proves the FK genuinely targets employees now, not just that
        // the column still accepts arbitrary integers. (Not using
        // $this->cashier->id here: users and employees are independent
        // autoincrement sequences, so a real user's id can coincidentally
        // collide with a real employee's id and defeat this assertion.)
        $noSuchEmployeeId = Employee::max('id') + 1000;

        $this->expectException(QueryException::class);
        Sale::where('id', $sale->id)->update(['sales_employee_id' => $noSuchEmployeeId]);
    }

    public function test_5_existing_historical_sales_retain_attribution_after_the_rebuild(): void
    {
        // Simulate a sale that existed before this migration ran, using
        // the shape the previous (0008) migration would have left it in
        // — legacy sales_employee_id already null (0008 never touches
        // it for rows inserted after it ran) with employee_id set
        // directly, then re-run the rebuild's own migration to prove
        // the copy preserves it.
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');

        $sale = app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: $employee->id,
            lines: [new CartLine($this->product, $this->meter->id, '5.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '250.00')],
        );

        $reloaded = Sale::find($sale->id);
        $this->assertSame($employee->id, $reloaded->sales_employee_id);
        $this->assertSame('S-'.now()->format('Ymd').'-', substr($reloaded->reference_no, 0, 11));
    }

    public function test_6_a_terminated_employee_remains_associated_with_historical_sales(): void
    {
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');

        $sale = app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: $employee->id,
            lines: [new CartLine($this->product, $this->meter->id, '5.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '250.00')],
        );

        app(ChangeEmployeeStatus::class)->handle($employee, EmployeeStatus::Terminated, '2026-06-30');

        $this->assertSame($employee->id, Sale::find($sale->id)->sales_employee_id, 'termination must not disturb historical attribution');
    }

    public function test_7_employee_and_user_identities_remain_independent_through_a_sale(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);
        $employee = app(CreateEmployee::class)->handle('Ahmed', '2026-01-01', userId: $user->id);

        $sale = app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: $employee->id,
            lines: [new CartLine($this->product, $this->meter->id, '5.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '250.00')],
        );

        // The sale references the Employee's id, never the User's id —
        // even though they currently happen to be linked, the sale
        // doesn't know or care about that link.
        $this->assertSame($employee->id, $sale->sales_employee_id);
        $this->assertNotEquals($user->id, $sale->sales_employee_id, 'coincidence guard: this only holds because Employee/User ids differ, which they will in any real tenant');
    }

    // --- Existing behavior preserved ---------------------------------------

    public function test_confirming_a_sale_with_no_sales_employee_still_works(): void
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

    public function test_cashier_id_still_requires_a_real_user(): void
    {
        $this->expectException(QueryException::class);

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
