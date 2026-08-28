<?php

namespace Tests\Feature\Employees;

use App\Models\User;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * Exercises the 0009 migration's own safety guard in true isolation: a
 * tenant migrated only through 0008 (the last migration before the
 * final rebuild), with a sale in the exact unsafe shape the guard
 * exists to catch — a legacy sales_employee_id with no corresponding
 * employee_id backfilled. 0009 must refuse to run, must not touch the
 * `sales` table at all, and must leave a clear reason.
 */
class FinalizeSalesEmployeeIdMigrationGuardTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private string $heldOutMigrationPath;
    private string $stashPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = storage_path('framework/testing/tenants-'.uniqid());
        config(['tenancy.tenant_database_path' => $this->tenantDbPath]);

        $this->heldOutMigrationPath = database_path('migrations/tenant/0009_01_01_000000_finalize_sales_employee_id_to_employees.php');
        $this->stashPath = storage_path('framework/testing/held-out-0009-'.uniqid().'.php');

        // Temporarily remove 0009 so `migrate` stops at 0008 — the exact
        // pre-rebuild shape this guard test needs to construct an unsafe
        // row against.
        File::move($this->heldOutMigrationPath, $this->stashPath);

        $tenant = Tenant::create(['name' => 'Al-Fateh Cloth House', 'slug' => 'alfateh', 'database' => 'alfateh', 'status' => 'active']);

        File::ensureDirectoryExists($this->tenantDbPath);
        File::put($this->tenantDbPath.'/alfateh.sqlite', '');

        app(TenantConnectionFactory::class)->useConnectionFor($tenant);
        config(['database.default' => 'tenant']);

        Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--realpath' => false, '--force' => true]);

        app(TenantContext::class)->set($tenant);
    }

    protected function tearDown(): void
    {
        // Restore 0009 before anything else, even if an assertion above failed.
        if (File::exists($this->stashPath)) {
            File::move($this->stashPath, $this->heldOutMigrationPath);
        }

        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    public function test_the_migration_refuses_to_run_when_a_legacy_attribution_cannot_be_verified(): void
    {
        $cashier = User::create(['name' => 'Cashier', 'email' => 'cashier@alfateh.test', 'password' => bcrypt('secret')]);
        $salesperson = User::create(['name' => 'Unmapped Salesperson', 'email' => 'unmapped@alfateh.test', 'password' => bcrypt('secret')]);
        $warehouse = Warehouse::create(['name' => 'Main Store']);

        // A real, existing user — but inserted directly here rather than
        // through 0008's backfill, so no Employee was ever created for
        // them. That's the exact unsafe shape: a legacy
        // sales_employee_id (→ a real user) with no employee_id at all.
        DB::table('sales')->insert([
            'warehouse_id' => $warehouse->id,
            'cashier_id' => $cashier->id,
            'sales_employee_id' => $salesperson->id,
            'employee_id' => null, // never backfilled — exactly the unsafe state
            'reference_no' => 'S-UNSAFE',
            'status' => 'confirmed',
            'subtotal' => 100, 'total' => 100, 'paid_total' => 100, 'balance_due' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require $this->stashPath;

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/sale #\d+.*has no employee_id backfilled/s');

            $migration->up();
        } finally {
            // Whether it threw or not, the sales table must be exactly
            // as it was — still the pre-rebuild shape, no sales_new
            // artifact left behind.
            $columns = collect(DB::connection('tenant')->select('PRAGMA table_info(sales)'))->pluck('name');
            $this->assertTrue($columns->contains('employee_id'), 'sales must remain in its pre-rebuild shape after a refused migration');
            $this->assertSame(0, DB::connection('tenant')->select("SELECT count(*) as c FROM sqlite_master WHERE type='table' AND name='sales_new'")[0]->c);
        }
    }
}
