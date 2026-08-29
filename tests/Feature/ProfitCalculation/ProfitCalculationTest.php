<?php

namespace Tests\Feature\ProfitCalculation;

use App\Models\User;
use App\Modules\Commission\Actions\CalculateCommissionForPeriod;
use App\Modules\Commission\Actions\CreateCommissionRule;
use App\Modules\Employees\Actions\CreateEmployee;
use App\Modules\Employees\Actions\RecordSalaryAccrual;
use App\Modules\Employees\Actions\RecordSalaryChange;
use App\Modules\Expenses\Actions\CreateExpenseCategory;
use App\Modules\Expenses\Actions\RecordExpense;
use App\Modules\FinancialPeriods\Actions\CreateFinancialPeriod;
use App\Modules\Inventory\Actions\RecordOpeningStock;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\ProfitCalculation\Actions\CalculateProfitForPeriod;
use App\Modules\ProfitCalculation\Actions\FinalizeProfitCalculation;
use App\Modules\ProfitCalculation\Enums\ProfitCalculationStatus;
use App\Modules\ProfitCalculation\Exceptions\CannotRecalculateFinalizedProfitException;
use App\Modules\ProfitCalculation\Exceptions\InvalidDistributableProfitException;
use App\Modules\ProfitCalculation\Exceptions\InvalidProfitCalculationTransitionException;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Sales\Actions\ConfirmSale;
use App\Modules\Sales\DTOs\CartLine;
use App\Modules\Sales\DTOs\PaymentAllocation;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProfitCalculationTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private Unit $meter;
    private Warehouse $warehouse;
    private Product $product;
    private User $cashier;
    private PaymentMethod $cash;
    private string $today;

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
        $this->today = Carbon::today()->toDateString();

        app(RecordOpeningStock::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '1000.0000', '20.0000');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    private function confirmSale(string $quantity, string $unitPrice): void
    {
        app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, $quantity, $unitPrice)],
            payments: [new PaymentAllocation($this->cash->id, bcmul($quantity, $unitPrice, 2))],
        );
    }

    public function test_a_full_period_combines_every_modules_totals(): void
    {
        // Sale: revenue 1000, cost 400, gross profit 600.
        $this->confirmSale('20.0000', '50.00');

        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(RecordSalaryChange::class)->handle($employee, '15000.00', '2026-01-01');
        app(CreateCommissionRule::class)->handle($employee, '10.00', $this->today);

        $expenseCategory = app(CreateExpenseCategory::class)->handle('Rent');
        app(RecordExpense::class)->handle($expenseCategory, '5000.00', $this->today, $this->cash->id, $this->cashier->id);

        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);

        app(RecordSalaryAccrual::class)->handle($employee, $period);
        app(CalculateCommissionForPeriod::class)->handle($period);

        // gross_profit 600, salary 15000, commission 60 (10% of 600),
        // other expenses 5000 -> net_profit = 600 - 15000 - 60 - 5000 = -19460
        $calculation = app(CalculateProfitForPeriod::class)->handle($period, '0.00');

        $this->assertSame('600.00', $calculation->gross_profit);
        $this->assertSame('15000.00', $calculation->salary_expense);
        $this->assertSame('60.00', $calculation->commission_expense);
        $this->assertSame('5000.00', $calculation->other_operating_expenses);
        $this->assertSame('-19460.00', $calculation->net_profit);
        $this->assertSame('0.00', $calculation->distributable_profit);
        $this->assertSame('-19460.00', $calculation->retained_profit);
        $this->assertSame(ProfitCalculationStatus::Draft, $calculation->status);
    }

    public function test_a_profitable_period_can_set_a_partial_distributable_amount(): void
    {
        // Revenue 5000, cost 2000, gross profit 3000. No salary/commission/expenses.
        $this->confirmSale('100.0000', '50.00');

        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);

        $calculation = app(CalculateProfitForPeriod::class)->handle($period, '2000.00');

        $this->assertSame('3000.00', $calculation->net_profit);
        $this->assertSame('2000.00', $calculation->distributable_profit);
        $this->assertSame('1000.00', $calculation->retained_profit);
    }

    public function test_distributable_profit_cannot_exceed_net_profit(): void
    {
        $this->confirmSale('100.0000', '50.00'); // gross profit 3000
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);

        $this->expectException(InvalidDistributableProfitException::class);

        app(CalculateProfitForPeriod::class)->handle($period, '3000.01');
    }

    public function test_distributable_profit_cannot_be_negative(): void
    {
        $this->confirmSale('100.0000', '50.00');
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);

        $this->expectException(InvalidDistributableProfitException::class);

        app(CalculateProfitForPeriod::class)->handle($period, '-1.00');
    }

    public function test_distributable_profit_must_be_zero_when_the_period_is_a_loss(): void
    {
        app(RecordExpense::class)->handle(
            app(CreateExpenseCategory::class)->handle('Rent'),
            '5000.00', $this->today, $this->cash->id, $this->cashier->id,
        );
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);

        $this->expectException(InvalidDistributableProfitException::class);

        app(CalculateProfitForPeriod::class)->handle($period, '100.00');
    }

    public function test_recalculating_a_draft_overwrites_it_rather_than_creating_a_second_row(): void
    {
        $this->confirmSale('100.0000', '50.00'); // gross profit 3000
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);

        app(CalculateProfitForPeriod::class)->handle($period, '1000.00');
        $recalculated = app(CalculateProfitForPeriod::class)->handle($period, '2000.00');

        $this->assertSame('2000.00', $recalculated->distributable_profit);
        $this->assertSame(1, \App\Modules\ProfitCalculation\Models\ProfitCalculation::where('financial_period_id', $period->id)->count());
    }

    public function test_finalizing_locks_the_calculation_against_recalculation(): void
    {
        $this->confirmSale('100.0000', '50.00');
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        $calculation = app(CalculateProfitForPeriod::class)->handle($period, '1000.00');

        app(FinalizeProfitCalculation::class)->handle($calculation);

        $this->assertSame(ProfitCalculationStatus::Finalized, $calculation->fresh()->status);

        $this->expectException(CannotRecalculateFinalizedProfitException::class);
        app(CalculateProfitForPeriod::class)->handle($period, '1500.00');
    }

    public function test_finalizing_twice_is_refused(): void
    {
        $this->confirmSale('100.0000', '50.00');
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        $calculation = app(CalculateProfitForPeriod::class)->handle($period, '1000.00');

        app(FinalizeProfitCalculation::class)->handle($calculation);

        $this->expectException(InvalidProfitCalculationTransitionException::class);
        app(FinalizeProfitCalculation::class)->handle($calculation->fresh());
    }
}
