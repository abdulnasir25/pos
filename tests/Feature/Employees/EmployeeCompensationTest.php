<?php

namespace Tests\Feature\Employees;

use App\Modules\Employees\Actions\ChangeEmployeeStatus;
use App\Modules\Employees\Actions\CreateEmployee;
use App\Modules\Employees\Actions\RecordSalaryChange;
use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Exceptions\InvalidCompensationRangeException;
use App\Modules\Employees\Exceptions\OverlappingCompensationException;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Support\ResolveSalaryForDate;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EmployeeCompensationTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private Employee $employee;

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

        $this->employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    public function test_first_salary_is_recorded_open_ended(): void
    {
        $comp = app(RecordSalaryChange::class)->handle($this->employee, '50000.00', '2026-01-01');

        $this->assertSame('50000.00', $comp->monthly_salary);
        $this->assertNull($comp->effective_to);
    }

    public function test_a_salary_change_closes_the_previously_open_record(): void
    {
        app(RecordSalaryChange::class)->handle($this->employee, '50000.00', '2026-01-01');
        app(RecordSalaryChange::class)->handle($this->employee, '60000.00', '2026-07-01');

        $this->employee->refresh();
        $history = $this->employee->compensationHistory()->orderBy('effective_from')->get();

        $this->assertCount(2, $history);
        $this->assertSame('2026-06-30', $history[0]->effective_to->toDateString());
        $this->assertNull($history[1]->effective_to);
    }

    public function test_historical_salary_resolves_correctly_across_a_change(): void
    {
        app(RecordSalaryChange::class)->handle($this->employee, '50000.00', '2026-01-01');
        app(RecordSalaryChange::class)->handle($this->employee, '60000.00', '2026-07-01');

        $resolver = app(ResolveSalaryForDate::class);

        $this->assertSame('50000.00', $resolver->handle($this->employee, '2026-03-15'));
        $this->assertSame('50000.00', $resolver->handle($this->employee, '2026-06-30'));
        $this->assertSame('60000.00', $resolver->handle($this->employee, '2026-07-01'));
        $this->assertSame('60000.00', $resolver->handle($this->employee, '2026-12-31'));
    }

    public function test_a_date_before_any_salary_resolves_to_null(): void
    {
        app(RecordSalaryChange::class)->handle($this->employee, '50000.00', '2026-01-01');

        $this->assertNull(app(ResolveSalaryForDate::class)->handle($this->employee, '2025-12-31'));
    }

    public function test_overlapping_compensation_against_a_closed_record_is_rejected(): void
    {
        app(RecordSalaryChange::class)->handle($this->employee, '50000.00', '2026-01-01', '2026-06-30');

        $this->expectException(OverlappingCompensationException::class);
        app(RecordSalaryChange::class)->handle($this->employee, '55000.00', '2026-03-01', '2026-09-30');
    }

    public function test_adjacent_compensation_periods_are_allowed(): void
    {
        app(RecordSalaryChange::class)->handle($this->employee, '50000.00', '2026-01-01', '2026-06-30');
        $second = app(RecordSalaryChange::class)->handle($this->employee, '55000.00', '2026-07-01', '2026-12-31');

        $this->assertSame('55000.00', $second->monthly_salary);
        $this->assertSame(2, $this->employee->compensationHistory()->count());
    }

    public function test_future_dated_compensation_is_accepted_and_closes_the_current_open_record(): void
    {
        app(RecordSalaryChange::class)->handle($this->employee, '50000.00', '2026-01-01');

        $future = app(RecordSalaryChange::class)->handle($this->employee, '65000.00', '2027-01-01');

        $this->assertSame('65000.00', $future->monthly_salary);
        $current = $this->employee->compensationHistory()->orderBy('effective_from')->first();
        $this->assertSame('2026-12-31', $current->effective_to->toDateString());
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->expectException(InvalidCompensationRangeException::class);
        app(RecordSalaryChange::class)->handle($this->employee, '50000.00', '2026-06-01', '2026-01-01');
    }

    public function test_terminated_employees_salary_history_remains_resolvable(): void
    {
        app(RecordSalaryChange::class)->handle($this->employee, '50000.00', '2026-01-01');
        app(ChangeEmployeeStatus::class)->handle($this->employee, EmployeeStatus::Terminated, '2026-06-30');

        $this->assertSame(
            '50000.00',
            app(ResolveSalaryForDate::class)->handle($this->employee->fresh(), '2026-03-01')
        );
    }
}
