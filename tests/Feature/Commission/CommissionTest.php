<?php

namespace Tests\Feature\Commission;

use App\Models\User;
use App\Modules\Commission\Actions\ApproveCommissionEntry;
use App\Modules\Commission\Actions\CalculateCommissionForPeriod;
use App\Modules\Commission\Actions\CreateCommissionRule;
use App\Modules\Commission\Actions\FinalizeCommissionEntry;
use App\Modules\Commission\Actions\RecordCommissionCorrection;
use App\Modules\Commission\Actions\RecordCommissionPayment;
use App\Modules\Commission\Enums\CommissionCorrectionReason;
use App\Modules\Commission\Enums\CommissionEntryStatus;
use App\Modules\Commission\Exceptions\CommissionAlreadyCalculatedException;
use App\Modules\Commission\Exceptions\CorrectionMustLandInAnOpenPeriodException;
use App\Modules\Commission\Exceptions\InvalidCommissionEntryTransitionException;
use App\Modules\Employees\Actions\CreateEmployee;
use App\Modules\Employees\Enums\EmployeeLedgerEntryType;
use App\Modules\FinancialPeriods\Actions\CreateFinancialPeriod;
use App\Modules\Inventory\Actions\RecordOpeningStock;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Sales\Actions\ConfirmSale;
use App\Modules\Sales\Actions\ReturnSaleItems;
use App\Modules\Sales\DTOs\CartLine;
use App\Modules\Sales\DTOs\PaymentAllocation;
use App\Modules\Sales\DTOs\ReturnLine;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CommissionTest extends TestCase
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

    private function confirmSale(string $quantity, string $unitPrice): \App\Modules\Sales\Models\Sale
    {
        return app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, $quantity, $unitPrice)],
            payments: [new PaymentAllocation($this->cash->id, bcmul($quantity, $unitPrice, 2))],
        );
    }

    // --- Calculation, tenant-wide basis ------------------------------------

    public function test_commission_is_based_on_the_whole_shops_profit_not_one_employees_sales(): void
    {
        // Two separate sales, neither attributed to the commission-earning
        // employee via sales_employee_id — the corrected rule is that
        // commission comes from the tenant's TOTAL profit, so attribution
        // is irrelevant to the calculation.
        $this->confirmSale('10.0000', '50.00'); // revenue 500, cost 200, profit 300
        $this->confirmSale('5.0000', '80.00');  // revenue 400, cost 100, profit 300

        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(CreateCommissionRule::class)->handle($employee, '10.00', $this->today);

        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        $entries = app(CalculateCommissionForPeriod::class)->handle($period);

        $this->assertCount(1, $entries);
        $entry = $entries->first();
        $this->assertSame('600.00', $entry->eligible_gross_profit);
        $this->assertSame('60.00', $entry->commission_amount);
        $this->assertSame(CommissionEntryStatus::Calculated, $entry->status);
    }

    public function test_commission_sale_lines_trace_every_sale_in_the_period(): void
    {
        $saleOne = $this->confirmSale('10.0000', '50.00');
        $saleTwo = $this->confirmSale('5.0000', '80.00');

        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(CreateCommissionRule::class)->handle($employee, '10.00', $this->today);

        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        $entry = app(CalculateCommissionForPeriod::class)->handle($period)->first();

        $this->assertCount(2, $entry->saleLines);
        $this->assertTrue($entry->saleLines->pluck('sale_id')->contains($saleOne->id));
        $this->assertTrue($entry->saleLines->pluck('sale_id')->contains($saleTwo->id));
    }

    public function test_a_returned_sale_reduces_the_eligible_gross_profit_before_calculation(): void
    {
        $sale = $this->confirmSale('10.0000', '50.00'); // revenue 500, cost 200, profit 300

        app(ReturnSaleItems::class)->handle(
            $sale,
            [new ReturnLine($sale->items->first()->id, '4.0000')],
            $this->cashier->id,
        );
        // Returned 4 of 10 units: revenue 500->300, cost 200->120, net profit 180.

        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(CreateCommissionRule::class)->handle($employee, '10.00', $this->today);

        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        $entry = app(CalculateCommissionForPeriod::class)->handle($period)->first();

        $this->assertSame('180.00', $entry->eligible_gross_profit);
        $this->assertSame('18.00', $entry->commission_amount);
    }

    public function test_commission_accrual_is_additive_to_salary_never_an_offset(): void
    {
        $this->confirmSale('10.0000', '50.00');

        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(CreateCommissionRule::class)->handle($employee, '10.00', $this->today);

        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        app(CalculateCommissionForPeriod::class)->handle($period);

        $ledgerEntry = $employee->ledgerEntries()->first();
        $this->assertSame(EmployeeLedgerEntryType::CommissionAccrual, $ledgerEntry->entry_type);
        $this->assertSame('30.00', $ledgerEntry->amount);
    }

    public function test_calculating_twice_for_the_same_period_is_refused(): void
    {
        $this->confirmSale('10.0000', '50.00');
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(CreateCommissionRule::class)->handle($employee, '10.00', $this->today);
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);

        app(CalculateCommissionForPeriod::class)->handle($period);

        $this->expectException(CommissionAlreadyCalculatedException::class);
        app(CalculateCommissionForPeriod::class)->handle($period);
    }

    public function test_no_commission_entries_are_created_when_no_employee_has_an_active_rule(): void
    {
        $this->confirmSale('10.0000', '50.00');
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);

        $entries = app(CalculateCommissionForPeriod::class)->handle($period);

        $this->assertCount(0, $entries);
    }

    // --- Status lifecycle ----------------------------------------------------

    public function test_full_lifecycle_calculated_to_approved_to_finalized_to_paid(): void
    {
        $this->confirmSale('10.0000', '50.00');
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(CreateCommissionRule::class)->handle($employee, '10.00', $this->today);
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        $entry = app(CalculateCommissionForPeriod::class)->handle($period)->first();

        $approver = User::create(['name' => 'Manager', 'email' => 'manager@alfateh.test', 'password' => bcrypt('secret')]);

        app(ApproveCommissionEntry::class)->handle($entry, $approver->id);
        $this->assertSame(CommissionEntryStatus::Approved, $entry->fresh()->status);

        app(FinalizeCommissionEntry::class)->handle($entry);
        $this->assertSame(CommissionEntryStatus::Finalized, $entry->fresh()->status);

        app(RecordCommissionPayment::class)->handle($entry);
        $this->assertSame(CommissionEntryStatus::Paid, $entry->fresh()->status);

        $paymentLedgerEntry = $employee->ledgerEntries()->where('entry_type', EmployeeLedgerEntryType::CommissionPayment)->first();
        $this->assertSame('-30.00', $paymentLedgerEntry->amount);
    }

    public function test_finalizing_before_approval_is_refused(): void
    {
        $this->confirmSale('10.0000', '50.00');
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(CreateCommissionRule::class)->handle($employee, '10.00', $this->today);
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        $entry = app(CalculateCommissionForPeriod::class)->handle($period)->first();

        $this->expectException(InvalidCommissionEntryTransitionException::class);
        app(FinalizeCommissionEntry::class)->handle($entry);
    }

    // --- Forward correction ---------------------------------------------------

    public function test_a_correction_after_finalization_lands_in_the_currently_open_period(): void
    {
        $this->confirmSale('10.0000', '50.00');
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(CreateCommissionRule::class)->handle($employee, '10.00', $this->today);
        $closedPeriod = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        $entry = app(CalculateCommissionForPeriod::class)->handle($closedPeriod)->first();
        app(ApproveCommissionEntry::class)->handle($entry, $this->cashier->id);
        app(FinalizeCommissionEntry::class)->handle($entry);

        $tomorrow = Carbon::tomorrow()->toDateString();
        $openPeriod = app(CreateFinancialPeriod::class)->handle($tomorrow, $tomorrow);

        $correction = app(RecordCommissionCorrection::class)->handle(
            $entry,
            $openPeriod,
            '-10.00',
            CommissionCorrectionReason::SaleReturn,
            $this->cashier->id,
        );

        $this->assertSame($openPeriod->id, $correction->financial_period_id);
        $this->assertNotEquals($entry->financial_period_id, $correction->financial_period_id);
        $this->assertSame('-10.00', $correction->amount);
    }

    public function test_a_correction_cannot_land_in_a_non_open_period(): void
    {
        $this->confirmSale('10.0000', '50.00');
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(CreateCommissionRule::class)->handle($employee, '10.00', $this->today);
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        $entry = app(CalculateCommissionForPeriod::class)->handle($period)->first();
        app(ApproveCommissionEntry::class)->handle($entry, $this->cashier->id);
        app(FinalizeCommissionEntry::class)->handle($entry);

        $this->expectException(CorrectionMustLandInAnOpenPeriodException::class);

        // The same (already-closed-for-commission-purposes-in-spirit but
        // still technically Open-status) period is fine to use for most
        // tests, so force a real non-open one via the period lifecycle.
        app(\App\Modules\FinancialPeriods\Actions\StartCalculation::class)->handle($period);

        app(RecordCommissionCorrection::class)->handle(
            $entry,
            $period->fresh(),
            '-10.00',
            CommissionCorrectionReason::SaleReturn,
            $this->cashier->id,
        );
    }
}
