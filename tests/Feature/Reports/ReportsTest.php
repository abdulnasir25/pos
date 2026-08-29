<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\FinancialPeriods\Actions\CreateFinancialPeriod;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\ProfitCalculation\Actions\CalculateProfitForPeriod;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Purchases\Actions\ConfirmPurchase;
use App\Modules\Purchases\DTOs\PurchaseLine;
use App\Modules\Purchases\DTOs\PurchasePaymentAllocation;
use App\Modules\Reports\Exceptions\ProfitCalculationNotFoundException;
use App\Modules\Reports\Support\OutstandingBalancesReportBuilder;
use App\Modules\Reports\Support\ProfitAndLossReportBuilder;
use App\Modules\Reports\Support\SalesSummaryReportBuilder;
use App\Modules\Reports\Support\StockLevelReportBuilder;
use App\Modules\Sales\Actions\ConfirmSale;
use App\Modules\Sales\DTOs\CartLine;
use App\Modules\Sales\DTOs\PaymentAllocation;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private Unit $meter;
    private Warehouse $warehouse;
    private Product $product;
    private User $admin;
    private PaymentMethod $cash;
    private PaymentMethod $bank;
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
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@alfateh.test', 'password' => bcrypt('secret')]);
        $this->cash = PaymentMethod::create(['name' => 'Cash']);
        $this->bank = PaymentMethod::create(['name' => 'Bank Transfer']);
        $this->today = Carbon::today()->toDateString();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    private function purchaseStock(string $quantity, string $unitCost): void
    {
        $supplier = Supplier::firstOrCreate(['name' => 'Faisalabad Textiles'], ['status' => 'active']);

        app(ConfirmPurchase::class)->handle(
            supplierId: $supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, $quantity, $unitCost)],
            payments: [new PurchasePaymentAllocation($this->cash->id, bcmul($quantity, $unitCost, 2))],
        );
    }

    // --- Sales summary ---------------------------------------------------

    public function test_sales_summary_totals_revenue_cogs_and_gross_profit(): void
    {
        $this->purchaseStock('1000.0000', '20.00');

        app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->admin->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.00')],
            payments: [new PaymentAllocation($this->cash->id, '500.00')],
        );

        $report = app(SalesSummaryReportBuilder::class)->build($this->today, $this->today);

        $this->assertSame('500.00', $report->revenue);
        $this->assertSame('200.00', $report->cogs);
        $this->assertSame('300.00', $report->grossProfit);
        $this->assertSame(1, $report->saleCount);
    }

    public function test_sales_summary_breaks_down_by_payment_method(): void
    {
        $this->purchaseStock('1000.0000', '20.00');

        app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->admin->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, '20.0000', '50.00')],
            payments: [
                new PaymentAllocation($this->cash->id, '600.00'),
                new PaymentAllocation($this->bank->id, '400.00'),
            ],
        );

        $report = app(SalesSummaryReportBuilder::class)->build($this->today, $this->today);

        $byMethod = collect($report->byPaymentMethod)->keyBy('method');
        $this->assertSame('600.00', $byMethod['Cash']['amount']);
        $this->assertSame('400.00', $byMethod['Bank Transfer']['amount']);
    }

    // --- Stock level -------------------------------------------------------

    public function test_stock_level_report_lists_only_products_with_nonzero_stock(): void
    {
        $this->purchaseStock('100.0000', '20.00');

        $report = app(StockLevelReportBuilder::class)->build();

        $this->assertCount(1, $report->rows);
        $this->assertSame('Cotton', $report->rows[0]['product']);
        $this->assertSame('100.0000', $report->rows[0]['quantity']);
        $this->assertSame('2000.00', $report->rows[0]['stock_value']);
        $this->assertSame('2000.00', $report->totalStockValue);
    }

    public function test_stock_level_report_excludes_fully_depleted_stock(): void
    {
        $this->purchaseStock('10.0000', '20.00');

        app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->admin->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.00')],
            payments: [new PaymentAllocation($this->cash->id, '500.00')],
        );

        $report = app(StockLevelReportBuilder::class)->build();

        $this->assertCount(0, $report->rows);
    }

    public function test_stock_level_report_can_filter_by_warehouse(): void
    {
        $secondWarehouse = Warehouse::create(['name' => 'Branch Store']);
        $this->purchaseStock('50.0000', '20.00');

        $filtered = app(StockLevelReportBuilder::class)->build($secondWarehouse->id);

        $this->assertCount(0, $filtered->rows);
    }

    // --- Profit and loss ----------------------------------------------------

    public function test_profit_and_loss_report_reflects_the_profit_calculation(): void
    {
        $this->purchaseStock('1000.0000', '20.00');
        app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->admin->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.00')],
            payments: [new PaymentAllocation($this->cash->id, '500.00')],
        );

        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);
        app(CalculateProfitForPeriod::class)->handle($period, '100.00');

        $report = app(ProfitAndLossReportBuilder::class)->build($period);

        $this->assertSame('300.00', $report->grossProfit);
        $this->assertSame('300.00', $report->netProfit);
        $this->assertSame('100.00', $report->distributableProfit);
        $this->assertSame('200.00', $report->retainedProfit);
        $this->assertSame('draft', $report->status);
    }

    public function test_profit_and_loss_report_throws_when_not_yet_calculated(): void
    {
        $period = app(CreateFinancialPeriod::class)->handle($this->today, $this->today);

        $this->expectException(ProfitCalculationNotFoundException::class);

        app(ProfitAndLossReportBuilder::class)->build($period);
    }

    // --- Outstanding balances -----------------------------------------------

    public function test_outstanding_balances_report_lists_only_customers_and_suppliers_who_owe_or_are_owed(): void
    {
        $settled = Customer::create(['name' => 'Settled Customer', 'balance' => '0.00', 'status' => 'active']);
        $owing = Customer::create(['name' => 'Owing Customer', 'balance' => '1500.00', 'status' => 'active']);
        Supplier::create(['name' => 'Paid Supplier', 'balance' => '0.00', 'status' => 'active']);
        $payable = Supplier::create(['name' => 'Owed Supplier', 'balance' => '3000.00', 'status' => 'active']);

        $report = app(OutstandingBalancesReportBuilder::class)->build();

        $this->assertCount(1, $report->customers);
        $this->assertSame('Owing Customer', $report->customers[0]['name']);
        $this->assertSame('1500.00', $report->totalReceivable);

        $this->assertCount(1, $report->suppliers);
        $this->assertSame('Owed Supplier', $report->suppliers[0]['name']);
        $this->assertSame('3000.00', $report->totalPayable);
    }
}
