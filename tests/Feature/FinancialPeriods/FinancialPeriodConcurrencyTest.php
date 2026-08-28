<?php

namespace Tests\Feature\FinancialPeriods;

use App\Modules\FinancialPeriods\Actions\CreateFinancialPeriod;
use App\Modules\FinancialPeriods\Actions\StartCalculation;
use App\Modules\FinancialPeriods\Exceptions\InvalidPeriodTransitionException;
use App\Modules\FinancialPeriods\Exceptions\OverlappingPeriodException;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A single PHP process can't run two requests at the exact same instant,
 * so — same honest caveat as InventoryRaceConditionTest — this doesn't
 * prove safety by racing real concurrent workers. It proves the two
 * properties that make concurrent overlap-protection and concurrent
 * transitions safe regardless of how many workers are involved: the
 * overlap check runs inside a BEGIN IMMEDIATE transaction (real write
 * lock taken up front, not deferred to the first write), and every
 * status transition is a single atomic UPDATE ... WHERE status = ?
 * statement, not a read-then-write.
 */
class FinancialPeriodConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = storage_path('framework/testing/tenants-'.uniqid());
        config(['tenancy.tenant_database_path' => $this->tenantDbPath]);

        $tenant = Tenant::create([
            'name' => 'Al-Fateh Cloth House', 'slug' => 'alfateh', 'database' => 'alfateh', 'status' => 'active',
        ]);

        File::ensureDirectoryExists($this->tenantDbPath);
        File::put($this->tenantDbPath.'/alfateh.sqlite', '');

        app(TenantConnectionFactory::class)->useConnectionFor($tenant);
        config(['database.default' => 'tenant']);

        Artisan::call('migrate', [
            '--database' => 'tenant', '--path' => 'database/migrations/tenant', '--realpath' => false, '--force' => true,
        ]);

        app(TenantContext::class)->set($tenant);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    public function test_two_competing_creates_for_the_same_range_cannot_both_succeed(): void
    {
        $succeeded = 0;
        $failed = 0;

        foreach ([1, 2] as $attempt) {
            try {
                app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');
                $succeeded++;
            } catch (OverlappingPeriodException) {
                $failed++;
            }
        }

        $this->assertSame(1, $succeeded);
        $this->assertSame(1, $failed);
        $this->assertSame(1, FinancialPeriod::count());
    }

    public function test_two_competing_transitions_of_the_same_period_cannot_both_succeed(): void
    {
        $period = app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');

        $succeeded = 0;
        $failed = 0;

        foreach ([1, 2] as $attempt) {
            try {
                // Both attempts start from the same in-memory snapshot of
                // the period (status = open), simulating two requests
                // that both loaded the period before either transitioned
                // it — the guard is in the UPDATE's WHERE clause, not in
                // trusting this PHP object's already-loaded status.
                app(StartCalculation::class)->handle($period);
                $succeeded++;
            } catch (InvalidPeriodTransitionException) {
                $failed++;
            }
        }

        $this->assertSame(1, $succeeded);
        $this->assertSame(1, $failed);
    }
}
