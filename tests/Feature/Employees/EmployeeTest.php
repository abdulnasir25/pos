<?php

namespace Tests\Feature\Employees;

use App\Models\User;
use App\Modules\Employees\Actions\ChangeEmployeeStatus;
use App\Modules\Employees\Actions\CreateEmployee;
use App\Modules\Employees\Actions\LinkEmployeeToUser;
use App\Modules\Employees\Actions\UnlinkEmployeeFromUser;
use App\Modules\Employees\Actions\UpdateEmployeeProfile;
use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

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
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    public function test_an_employee_can_be_created_without_a_user(): void
    {
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');

        $this->assertNull($employee->user_id);
        $this->assertSame(EmployeeStatus::Active, $employee->status);
        $this->assertSame('Tailor Ahmed', $employee->name);
    }

    public function test_an_employee_can_be_created_with_a_user(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);

        $employee = app(CreateEmployee::class)->handle('Ahmed', '2026-01-01', userId: $user->id);

        $this->assertSame($user->id, $employee->user_id);
    }

    public function test_an_employee_can_be_linked_to_a_user_after_creation(): void
    {
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);

        $linked = app(LinkEmployeeToUser::class)->handle($employee, $user->id);

        $this->assertSame($user->id, $linked->user_id);
    }

    public function test_an_employee_can_be_unlinked_from_a_user_without_deleting_either(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);
        $employee = app(CreateEmployee::class)->handle('Ahmed', '2026-01-01', userId: $user->id);

        $unlinked = app(UnlinkEmployeeFromUser::class)->handle($employee);

        $this->assertNull($unlinked->user_id);
        $this->assertNotNull(Employee::find($employee->id), 'unlinking must not delete the employee');
        $this->assertNotNull(User::find($user->id), 'unlinking must not delete the user');
    }

    public function test_disabling_login_access_does_not_affect_employment(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);
        $employee = app(CreateEmployee::class)->handle('Ahmed', '2026-01-01', userId: $user->id);

        app(UnlinkEmployeeFromUser::class)->handle($employee);

        $this->assertSame(EmployeeStatus::Active, $employee->fresh()->status, 'losing login access must not change employment status');
    }

    public function test_status_can_change_to_terminated_and_sets_terminated_at(): void
    {
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');

        $terminated = app(ChangeEmployeeStatus::class)->handle($employee, EmployeeStatus::Terminated, '2026-06-30');

        $this->assertSame(EmployeeStatus::Terminated, $terminated->status);
        $this->assertSame('2026-06-30', $terminated->terminated_at->toDateString());
    }

    public function test_reactivating_a_terminated_employee_clears_terminated_at(): void
    {
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        $terminated = app(ChangeEmployeeStatus::class)->handle($employee, EmployeeStatus::Terminated, '2026-06-30');

        $reactivated = app(ChangeEmployeeStatus::class)->handle($terminated, EmployeeStatus::Active);

        $this->assertSame(EmployeeStatus::Active, $reactivated->status);
        $this->assertNull($reactivated->terminated_at);
    }

    public function test_terminating_an_employee_does_not_delete_the_record(): void
    {
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(ChangeEmployeeStatus::class)->handle($employee, EmployeeStatus::Terminated, '2026-06-30');

        $this->assertSame(1, Employee::count());
        $this->assertNotNull(Employee::find($employee->id));
    }

    public function test_profile_fields_can_be_updated(): void
    {
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01', '0300-0000000');

        $updated = app(UpdateEmployeeProfile::class)->handle($employee, 'Ahmed Khan', '0300-1111111');

        $this->assertSame('Ahmed Khan', $updated->name);
        $this->assertSame('0300-1111111', $updated->phone);
    }

    public function test_linking_to_a_nonexistent_user_is_rejected_by_the_foreign_key(): void
    {
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');

        $this->expectException(QueryException::class);
        app(LinkEmployeeToUser::class)->handle($employee, 999999);
    }

    public function test_tenant_a_cannot_see_tenant_bs_employees(): void
    {
        app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        $this->assertSame(1, Employee::count());

        $tenantB = Tenant::create(['name' => 'Zainab Fabrics', 'slug' => 'zainab', 'database' => 'zainab', 'status' => 'active']);
        File::put($this->tenantDbPath.'/zainab.sqlite', '');
        app(TenantConnectionFactory::class)->useConnectionFor($tenantB);
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--realpath' => false, '--force' => true]);
        app(TenantContext::class)->set($tenantB);

        $this->assertSame(0, Employee::count());
    }
}
