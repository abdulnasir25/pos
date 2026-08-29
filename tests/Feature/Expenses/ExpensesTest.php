<?php

namespace Tests\Feature\Expenses;

use App\Models\User;
use App\Modules\Expenses\Actions\CreateExpenseCategory;
use App\Modules\Expenses\Actions\RecordExpense;
use App\Modules\Expenses\Actions\RecordExpenseCorrection;
use App\Modules\Expenses\Exceptions\DuplicateExpenseCategoryNameException;
use App\Modules\Expenses\Exceptions\InvalidExpenseAmountException;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Support\SumExpensesForDateRange;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExpensesTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private User $admin;
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

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@alfateh.test', 'password' => bcrypt('secret')]);
        $this->cash = PaymentMethod::create(['name' => 'Cash']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    public function test_an_expense_category_can_be_created(): void
    {
        $category = app(CreateExpenseCategory::class)->handle('Rent');

        $this->assertSame('Rent', $category->name);
    }

    public function test_a_duplicate_category_name_is_refused(): void
    {
        app(CreateExpenseCategory::class)->handle('Rent');

        $this->expectException(DuplicateExpenseCategoryNameException::class);

        app(CreateExpenseCategory::class)->handle('Rent');
    }

    public function test_an_expense_can_be_recorded(): void
    {
        $category = app(CreateExpenseCategory::class)->handle('Utilities');

        $expense = app(RecordExpense::class)->handle($category, '5000.00', '2026-01-15', $this->cash->id, $this->admin->id, 'January electricity bill');

        $this->assertSame('5000.00', $expense->amount);
        $this->assertSame('January electricity bill', $expense->description);
        $this->assertSame($category->id, $expense->expense_category_id);
    }

    public function test_a_zero_or_negative_expense_amount_is_refused(): void
    {
        $category = app(CreateExpenseCategory::class)->handle('Utilities');

        $this->expectException(InvalidExpenseAmountException::class);

        app(RecordExpense::class)->handle($category, '0.00', '2026-01-15', $this->cash->id, $this->admin->id);
    }

    public function test_a_correction_creates_a_new_row_referencing_the_original(): void
    {
        $category = app(CreateExpenseCategory::class)->handle('Utilities');
        $original = app(RecordExpense::class)->handle($category, '5000.00', '2026-01-15', $this->cash->id, $this->admin->id);

        $correction = app(RecordExpenseCorrection::class)->handle($original, '-500.00', $this->admin->id, 'Billing error refund');

        $this->assertSame('-500.00', $correction->amount);
        $this->assertSame($original->id, $correction->reference_id);
        $this->assertSame(Expense::class, $correction->reference_type);
        $this->assertSame('5000.00', $original->fresh()->amount, 'the original row must never be edited');
    }

    public function test_summing_expenses_for_a_date_range_nets_corrections(): void
    {
        // RecordExpenseCorrection dates the correction "today" — when it
        // was recorded, not the original expense's date — so the range
        // here must cover both.
        $category = app(CreateExpenseCategory::class)->handle('Utilities');
        $original = app(RecordExpense::class)->handle($category, '5000.00', '2026-01-15', $this->cash->id, $this->admin->id);
        app(RecordExpenseCorrection::class)->handle($original, '-500.00', $this->admin->id);

        $total = app(SumExpensesForDateRange::class)->handle('2026-01-01', now()->toDateString());

        $this->assertSame('4500.00', $total);
    }

    public function test_summing_excludes_expenses_outside_the_date_range(): void
    {
        $category = app(CreateExpenseCategory::class)->handle('Rent');
        app(RecordExpense::class)->handle($category, '10000.00', '2026-02-01', $this->cash->id, $this->admin->id);

        $total = app(SumExpensesForDateRange::class)->handle('2026-01-01', '2026-01-31');

        $this->assertSame('0.00', $total);
    }

    public function test_summing_includes_expenses_exactly_on_the_range_boundary(): void
    {
        // Regression coverage for the same boundary-comparison bug class
        // fixed in CreateFinancialPeriod/AllocateProfitToPartners.
        $category = app(CreateExpenseCategory::class)->handle('Rent');
        app(RecordExpense::class)->handle($category, '10000.00', '2026-01-31', $this->cash->id, $this->admin->id);

        $total = app(SumExpensesForDateRange::class)->handle('2026-01-01', '2026-01-31');

        $this->assertSame('10000.00', $total);
    }
}
