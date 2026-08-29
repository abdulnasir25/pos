<?php

namespace Tests\Feature\Expenses;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Expenses\Actions\CreateExpenseCategory;
use App\Modules\Expenses\Actions\RecordExpense;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExpensesControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private string $baseUrl;
    private Tenant $tenant;
    private User $managerUser;
    private PaymentMethod $cash;

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

        $this->cash = PaymentMethod::create(['name' => 'Cash']);

        $this->managerUser = User::create(['name' => 'Manager', 'email' => 'manager@alfateh.test', 'password' => bcrypt('secret')]);
        $managerRole = Role::where('slug', 'manager')->first();
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'expenses.manage')->first());
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

    public function test_the_expenses_manage_permission_exists_in_the_baseline_seed(): void
    {
        $this->assertNotNull(Permission::where('slug', 'expenses.manage')->first());
    }

    public function test_a_user_without_expenses_manage_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->get("{$this->baseUrl}/expenses");

        $response->assertForbidden();
    }

    public function test_the_expenses_page_lists_recent_expenses_and_the_total(): void
    {
        $category = app(CreateExpenseCategory::class)->handle('Rent');
        app(RecordExpense::class)->handle($category, '5000.00', '2026-01-15', $this->cash->id, $this->managerUser->id, 'January rent');
        $this->login();

        $response = $this->get("{$this->baseUrl}/expenses");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Expenses/Index')
            ->has('expenses', 1)
            ->where('expenses.0.category', 'Rent')
            ->where('total', '5000.00')
        );
    }

    public function test_a_category_can_be_added_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/expenses/categories", ['name' => 'Utilities']);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, ExpenseCategory::where('name', 'Utilities')->count());
    }

    public function test_a_duplicate_category_name_shows_an_error_not_a_crash(): void
    {
        app(CreateExpenseCategory::class)->handle('Rent');
        $this->login();

        $response = $this->post("{$this->baseUrl}/expenses/categories", ['name' => 'Rent']);

        $response->assertSessionHasErrors('category');
    }

    public function test_an_expense_can_be_recorded_through_the_form(): void
    {
        $category = app(CreateExpenseCategory::class)->handle('Rent');
        $this->login();

        $response = $this->post("{$this->baseUrl}/expenses", [
            'expense_category_id' => $category->id,
            'amount' => 5000,
            'expense_date' => '2026-01-15',
            'payment_method_id' => $this->cash->id,
            'description' => 'January rent',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, Expense::count());
    }

    public function test_a_negligible_amount_that_rounds_to_zero_shows_an_error_not_a_crash(): void
    {
        $category = app(CreateExpenseCategory::class)->handle('Rent');
        $this->login();

        // 0.001 passes the 'gt:0' validation rule but rounds to 0.00 at
        // the 2dp scale RecordExpense's own guard uses — must surface
        // as a graceful error, not a 500.
        $response = $this->post("{$this->baseUrl}/expenses", [
            'expense_category_id' => $category->id,
            'amount' => 0.001,
            'expense_date' => '2026-01-15',
            'payment_method_id' => $this->cash->id,
        ]);

        $response->assertSessionHasErrors('expense');
        $this->resumeTenantContext();
        $this->assertSame(0, Expense::count());
    }

    public function test_a_correction_can_be_recorded_through_the_form(): void
    {
        $category = app(CreateExpenseCategory::class)->handle('Rent');
        $original = app(RecordExpense::class)->handle($category, '5000.00', '2026-01-15', $this->cash->id, $this->managerUser->id);
        $this->login();

        $response = $this->post("{$this->baseUrl}/expenses/corrections", [
            'expense_id' => $original->id,
            'amount' => -500,
            'description' => 'Billing error refund',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(2, Expense::count());
        $this->assertSame('5000.00', $original->fresh()->amount, 'the original row must never be edited');
    }
}
