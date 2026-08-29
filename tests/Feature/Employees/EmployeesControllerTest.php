<?php

namespace Tests\Feature\Employees;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\EmployeeCompensation;
use App\Modules\Employees\Models\SalaryPayment;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EmployeesControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private string $baseUrl;
    private Tenant $tenant;
    private User $managerUser;
    private PaymentMethod $cash;
    private FinancialPeriod $period;

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

        $this->cash = PaymentMethod::create(['name' => 'Cash', 'status' => 'active']);
        $this->period = FinancialPeriod::create(['period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'status' => 'open']);

        $this->managerUser = User::create(['name' => 'Manager', 'email' => 'manager@alfateh.test', 'password' => bcrypt('secret')]);
        $managerRole = Role::where('slug', 'manager')->first();
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'employees.view')->first());
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'employees.manage')->first());
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'salaries.manage')->first());
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

    public function test_a_user_without_employees_view_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->get("{$this->baseUrl}/employees");

        $response->assertForbidden();
    }

    public function test_the_page_lists_employees_with_their_current_salary(): void
    {
        $employee = Employee::create(['name' => 'Bilal', 'hired_at' => '2026-01-01', 'status' => 'active']);
        EmployeeCompensation::create(['employee_id' => $employee->id, 'monthly_salary' => '30000.00', 'effective_from' => '2026-01-01']);
        $this->login();

        $response = $this->get("{$this->baseUrl}/employees");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Employees/Index')
            ->has('employees', 1)
            ->where('employees.0.current_salary', '30000.00')
        );
    }

    public function test_an_employee_can_be_added_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/employees", [
            'name' => 'Bilal',
            'hired_at' => '2026-01-01',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('active', Employee::where('name', 'Bilal')->first()->status->value);
    }

    public function test_a_salary_can_be_recorded_through_the_form(): void
    {
        $employee = Employee::create(['name' => 'Bilal', 'hired_at' => '2026-01-01', 'status' => 'active']);
        $this->login();

        $response = $this->post("{$this->baseUrl}/employees/{$employee->id}/salary", [
            'monthly_salary' => '30000',
            'effective_from' => '2026-01-01',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, EmployeeCompensation::where('employee_id', $employee->id)->count());
    }

    public function test_an_overlapping_salary_change_shows_an_error_not_a_crash(): void
    {
        $employee = Employee::create(['name' => 'Bilal', 'hired_at' => '2026-01-01', 'status' => 'active']);
        EmployeeCompensation::create(['employee_id' => $employee->id, 'monthly_salary' => '30000.00', 'effective_from' => '2026-01-01', 'effective_to' => '2026-06-30']);
        $this->login();

        $response = $this->post("{$this->baseUrl}/employees/{$employee->id}/salary", [
            'monthly_salary' => '35000',
            'effective_from' => '2026-03-01',
        ]);

        $response->assertSessionHasErrors('salary');
    }

    public function test_an_employee_status_can_be_changed_through_the_form(): void
    {
        $employee = Employee::create(['name' => 'Bilal', 'hired_at' => '2026-01-01', 'status' => 'active']);
        $this->login();

        $response = $this->post("{$this->baseUrl}/employees/{$employee->id}/status", [
            'status' => 'terminated',
            'terminated_at' => '2026-06-01',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $fresh = $employee->fresh();
        $this->assertSame('terminated', $fresh->status->value);
        $this->assertSame('2026-06-01', $fresh->terminated_at->toDateString());
    }

    public function test_a_salary_payment_can_be_recorded_through_the_form(): void
    {
        $employee = Employee::create(['name' => 'Bilal', 'hired_at' => '2026-01-01', 'status' => 'active']);
        $this->login();

        $response = $this->post("{$this->baseUrl}/employees/{$employee->id}/salary-payments", [
            'financial_period_id' => $this->period->id,
            'amount' => '30000',
            'payment_method_id' => $this->cash->id,
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, SalaryPayment::where('employee_id', $employee->id)->count());
    }

    public function test_salary_actions_are_forbidden_without_salaries_manage_permission(): void
    {
        $viewOnlyUser = User::create(['name' => 'ViewOnly', 'email' => 'viewonly@alfateh.test', 'password' => bcrypt('secret')]);
        $viewOnlyRole = Role::where('slug', 'cashier')->first();
        app(GrantPermissionToRole::class)->handle($viewOnlyRole, Permission::where('slug', 'employees.view')->first());
        app(AssignRoleToUser::class)->handle($viewOnlyUser, $viewOnlyRole);
        $employee = Employee::create(['name' => 'Bilal', 'hired_at' => '2026-01-01', 'status' => 'active']);

        $response = $this->actingAs($viewOnlyUser)->post("{$this->baseUrl}/employees/{$employee->id}/salary", [
            'monthly_salary' => '30000',
            'effective_from' => '2026-01-01',
        ]);

        $response->assertForbidden();
    }
}
