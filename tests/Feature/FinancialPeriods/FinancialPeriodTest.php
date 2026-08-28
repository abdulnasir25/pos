<?php

namespace Tests\Feature\FinancialPeriods;

use App\Models\User;
use App\Modules\FinancialPeriods\Actions\ApplyPeriodTransition;
use App\Modules\FinancialPeriods\Actions\ClosePeriod;
use App\Modules\FinancialPeriods\Actions\CreateFinancialPeriod;
use App\Modules\FinancialPeriods\Actions\MoveToReview;
use App\Modules\FinancialPeriods\Actions\StartCalculation;
use App\Modules\FinancialPeriods\Enums\FinancialPeriodStatus;
use App\Modules\FinancialPeriods\Exceptions\InvalidPeriodRangeException;
use App\Modules\FinancialPeriods\Exceptions\InvalidPeriodTransitionException;
use App\Modules\FinancialPeriods\Exceptions\OverlappingPeriodException;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Exceptions\NoTenantResolvedException;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FinancialPeriodTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private User $reviewer;

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

        $this->reviewer = User::create(['name' => 'Manager', 'email' => 'manager@alfateh.test', 'password' => bcrypt('secret')]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    // --- Creation ---------------------------------------------------

    public function test_a_valid_period_can_be_created(): void
    {
        $period = app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');

        $this->assertSame(FinancialPeriodStatus::Open, $period->status);
        $this->assertSame('2026-01-01', $period->period_start->toDateString());
        $this->assertSame('2026-01-31', $period->period_end->toDateString());
        $this->assertNull($period->calculated_at);
        $this->assertNull($period->reviewed_by);
        $this->assertNull($period->closed_at);
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $this->expectException(InvalidPeriodRangeException::class);

        app(CreateFinancialPeriod::class)->handle('2026-01-31', '2026-01-01');
    }

    public function test_duplicate_date_range_is_rejected(): void
    {
        app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');

        $this->expectException(OverlappingPeriodException::class);

        app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');
    }

    public function test_overlapping_date_range_is_rejected(): void
    {
        app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');

        $this->expectException(OverlappingPeriodException::class);

        app(CreateFinancialPeriod::class)->handle('2026-01-15', '2026-02-15');
    }

    public function test_a_period_fully_containing_an_existing_one_is_rejected(): void
    {
        app(CreateFinancialPeriod::class)->handle('2026-01-10', '2026-01-20');

        $this->expectException(OverlappingPeriodException::class);

        app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');
    }

    public function test_adjacent_non_overlapping_periods_are_both_allowed(): void
    {
        app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');
        $second = app(CreateFinancialPeriod::class)->handle('2026-02-01', '2026-02-28');

        $this->assertSame('2026-02-01', $second->period_start->toDateString());
        $this->assertSame(2, FinancialPeriod::count());
    }

    public function test_creating_a_period_without_a_resolved_tenant_throws(): void
    {
        app(TenantContext::class)->clear();

        $this->expectException(NoTenantResolvedException::class);

        app(CreateFinancialPeriod::class)->handle('2026-03-01', '2026-03-31');
    }

    // --- Lifecycle ----------------------------------------------------

    public function test_full_valid_lifecycle(): void
    {
        $period = app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');

        $calculating = app(StartCalculation::class)->handle($period);
        $this->assertSame(FinancialPeriodStatus::Calculating, $calculating->status);
        $this->assertNotNull($calculating->calculated_at);

        $underReview = app(MoveToReview::class)->handle($calculating, $this->reviewer->id);
        $this->assertSame(FinancialPeriodStatus::UnderReview, $underReview->status);
        $this->assertSame($this->reviewer->id, $underReview->reviewed_by);
        $this->assertNull($underReview->closed_at, 'moving to review must not close the period');

        $closed = app(ClosePeriod::class)->handle($underReview);
        $this->assertSame(FinancialPeriodStatus::Closed, $closed->status);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_starting_calculation_preserves_the_period_dates(): void
    {
        $period = app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');

        $calculating = app(StartCalculation::class)->handle($period);

        $this->assertSame('2026-01-01', $calculating->period_start->toDateString());
        $this->assertSame('2026-01-31', $calculating->period_end->toDateString());
    }

    // --- Invalid transitions -------------------------------------------

    public function test_open_cannot_go_directly_to_under_review(): void
    {
        $period = app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');

        $this->expectException(InvalidPeriodTransitionException::class);
        app(MoveToReview::class)->handle($period, $this->reviewer->id);
    }

    public function test_open_cannot_go_directly_to_closed(): void
    {
        $period = app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');

        $this->expectException(InvalidPeriodTransitionException::class);
        app(ClosePeriod::class)->handle($period);
    }

    public function test_calculating_cannot_go_directly_to_closed(): void
    {
        $period = app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');
        $calculating = app(StartCalculation::class)->handle($period);

        $this->expectException(InvalidPeriodTransitionException::class);
        app(ClosePeriod::class)->handle($calculating);
    }

    public function test_under_review_cannot_go_back_to_open(): void
    {
        $period = app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');
        $calculating = app(StartCalculation::class)->handle($period);
        $underReview = app(MoveToReview::class)->handle($calculating, $this->reviewer->id);

        $this->expectException(InvalidPeriodTransitionException::class);
        app(ApplyPeriodTransition::class)->handle($underReview, FinancialPeriodStatus::Open);
    }

    public function test_calculating_cannot_start_calculation_again(): void
    {
        $period = app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');
        $calculating = app(StartCalculation::class)->handle($period);

        $this->expectException(InvalidPeriodTransitionException::class);
        app(StartCalculation::class)->handle($calculating);
    }

    // --- Closed immutability --------------------------------------------

    public function test_closed_period_cannot_be_closed_again(): void
    {
        $period = $this->closedPeriod();

        $this->expectException(InvalidPeriodTransitionException::class);
        app(ClosePeriod::class)->handle($period);
    }

    public function test_closed_period_cannot_move_to_review_again(): void
    {
        $period = $this->closedPeriod();

        $this->expectException(InvalidPeriodTransitionException::class);
        app(MoveToReview::class)->handle($period, $this->reviewer->id);
    }

    public function test_closed_period_cannot_start_calculation(): void
    {
        $period = $this->closedPeriod();

        $this->expectException(InvalidPeriodTransitionException::class);
        app(StartCalculation::class)->handle($period);
    }

    public function test_a_new_overlapping_period_is_still_rejected_even_once_the_old_one_is_closed(): void
    {
        $this->closedPeriod(); // 2026-01-01 .. 2026-01-31, closed

        $this->expectException(OverlappingPeriodException::class);
        app(CreateFinancialPeriod::class)->handle('2026-01-15', '2026-02-15');
    }

    private function closedPeriod(): FinancialPeriod
    {
        $period = app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');
        $calculating = app(StartCalculation::class)->handle($period);
        $underReview = app(MoveToReview::class)->handle($calculating, $this->reviewer->id);

        return app(ClosePeriod::class)->handle($underReview);
    }

    // --- Tenant isolation -------------------------------------------------

    public function test_tenant_a_cannot_see_tenant_bs_financial_periods(): void
    {
        app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');
        $this->assertSame(1, FinancialPeriod::count());

        $tenantB = Tenant::create(['name' => 'Zainab Fabrics', 'slug' => 'zainab', 'database' => 'zainab', 'status' => 'active']);
        File::put($this->tenantDbPath.'/zainab.sqlite', '');
        app(TenantConnectionFactory::class)->useConnectionFor($tenantB);
        Artisan::call('migrate', [
            '--database' => 'tenant', '--path' => 'database/migrations/tenant', '--realpath' => false, '--force' => true,
        ]);
        app(TenantContext::class)->set($tenantB);

        $this->assertSame(0, FinancialPeriod::count(), 'a fresh tenant database must not see the other tenant\'s periods');

        // The same date range that was already taken in tenant A must be
        // perfectly fine in tenant B — proof the overlap check is scoped
        // to the current tenant connection, not global.
        $periodInB = app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');
        $this->assertNotNull($periodInB->id);
    }
}
