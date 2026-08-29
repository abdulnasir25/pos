<?php

namespace Tests\Feature\Purchases;

use App\Models\User;
use App\Modules\Employees\Actions\CreateEmployee;
use App\Modules\Inventory\Support\StockLevelService;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Purchases\Actions\CancelPurchase;
use App\Modules\Purchases\Actions\ConfirmPurchase;
use App\Modules\Purchases\Actions\ReturnPurchaseItems;
use App\Modules\Purchases\DTOs\PurchaseLine;
use App\Modules\Purchases\DTOs\PurchasePaymentAllocation;
use App\Modules\Purchases\DTOs\PurchaseReturnLine;
use App\Modules\Purchases\Enums\PurchaseStatus;
use App\Modules\Purchases\Exceptions\InvalidPurchaseStateException;
use App\Modules\Purchases\Exceptions\OverpaymentException;
use App\Modules\Purchases\Exceptions\ReturnQuantityExceedsAvailableException;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PurchasesModuleTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private Unit $meter;
    private Warehouse $warehouse;
    private Product $product;
    private User $admin;
    private PaymentMethod $cash;
    private PaymentMethod $bank;
    private Supplier $supplier;

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
        $this->supplier = Supplier::create(['name' => 'Faisalabad Textiles', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    // --- Confirming ------------------------------------------------------

    public function test_a_fully_paid_purchase_increases_stock_and_leaves_no_payable(): void
    {
        $purchase = app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '100.0000', '20.00')],
            payments: [new PurchasePaymentAllocation($this->cash->id, '2000.00')],
        );

        $this->assertSame(PurchaseStatus::Confirmed, $purchase->status);
        $this->assertSame('2000.00', $purchase->total);
        $this->assertSame('0.00', $purchase->balance_payable);

        $stock = app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id);
        $this->assertSame('100.0000', $stock);

        $this->assertSame('0.00', $this->supplier->fresh()->balance);
    }

    public function test_a_partially_paid_purchase_increases_the_supplier_balance(): void
    {
        app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '100.0000', '20.00')],
            payments: [new PurchasePaymentAllocation($this->cash->id, '1500.00')],
        );

        $this->assertSame('500.00', $this->supplier->fresh()->balance);
    }

    public function test_an_unpaid_purchase_is_allowed_with_the_full_amount_payable(): void
    {
        $purchase = app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '50.0000', '20.00')],
            payments: [],
        );

        $this->assertSame('1000.00', $purchase->balance_payable);
        $this->assertSame('1000.00', $this->supplier->fresh()->balance);
    }

    public function test_payments_exceeding_the_total_are_refused(): void
    {
        $this->expectException(OverpaymentException::class);

        app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '10.0000', '20.00')],
            payments: [new PurchasePaymentAllocation($this->cash->id, '500.00')],
        );
    }

    public function test_multiple_payment_methods_can_split_one_purchase(): void
    {
        $purchase = app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '100.0000', '20.00')],
            payments: [
                new PurchasePaymentAllocation($this->cash->id, '1000.00'),
                new PurchasePaymentAllocation($this->bank->id, '1000.00'),
            ],
        );

        $this->assertCount(2, $purchase->payments);
        $this->assertSame('2000.00', $purchase->paid_total);
    }

    public function test_an_employee_can_be_recorded_as_the_one_who_made_the_purchase(): void
    {
        $employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');

        $purchase = app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: $employee->id,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '10.0000', '20.00')],
            payments: [new PurchasePaymentAllocation($this->cash->id, '200.00')],
        );

        $this->assertSame($employee->id, $purchase->employee_id);
    }

    public function test_purchasing_sets_the_new_weighted_average_cost(): void
    {
        app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '100.0000', '20.00')],
            payments: [new PurchasePaymentAllocation($this->cash->id, '2000.00')],
        );

        app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '100.0000', '30.00')],
            payments: [new PurchasePaymentAllocation($this->cash->id, '3000.00')],
        );

        // Weighted average: (100*20 + 100*30) / 200 = 25.00
        $movement = \App\Modules\Inventory\Models\InventoryMovement::latest('id')->first();
        $this->assertSame('25.0000', $movement->unit_cost);
    }

    // --- Cancelling ------------------------------------------------------

    public function test_cancelling_a_purchase_reverses_stock_and_the_supplier_balance(): void
    {
        $purchase = app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '100.0000', '20.00')],
            payments: [new PurchasePaymentAllocation($this->cash->id, '1000.00')],
        );

        $this->assertSame('1000.00', $this->supplier->fresh()->balance);

        app(CancelPurchase::class)->handle($purchase, $this->admin->id);

        $this->assertSame(PurchaseStatus::Cancelled, $purchase->fresh()->status);
        $this->assertSame('0.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
        $this->assertSame('0.00', $this->supplier->fresh()->balance);
    }

    public function test_cancelling_an_already_cancelled_purchase_is_refused(): void
    {
        $purchase = app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '10.0000', '20.00')],
            payments: [new PurchasePaymentAllocation($this->cash->id, '200.00')],
        );

        app(CancelPurchase::class)->handle($purchase, $this->admin->id);

        $this->expectException(InvalidPurchaseStateException::class);
        app(CancelPurchase::class)->handle($purchase->fresh(), $this->admin->id);
    }

    // --- Returning ---------------------------------------------------------

    public function test_a_partial_return_reduces_stock_and_supplier_balance_proportionally(): void
    {
        $purchase = app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '100.0000', '20.00')],
            payments: [],
        );

        $return = app(ReturnPurchaseItems::class)->handle(
            $purchase,
            [new PurchaseReturnLine($purchase->items->first()->id, '40.0000')],
            $this->admin->id,
        );

        $this->assertSame('800.00', $return->credit_amount);
        $this->assertSame('60.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
        $this->assertSame('1200.00', $this->supplier->fresh()->balance);
        $this->assertSame(PurchaseStatus::Confirmed, $purchase->fresh()->status, 'a partial return must not close the purchase');
    }

    public function test_a_full_return_marks_the_purchase_returned(): void
    {
        $purchase = app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '100.0000', '20.00')],
            payments: [],
        );

        app(ReturnPurchaseItems::class)->handle(
            $purchase,
            [new PurchaseReturnLine($purchase->items->first()->id, '100.0000')],
            $this->admin->id,
        );

        $this->assertSame(PurchaseStatus::Returned, $purchase->fresh()->status);
    }

    public function test_returning_more_than_purchased_is_refused(): void
    {
        $purchase = app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '10.0000', '20.00')],
            payments: [],
        );

        $this->expectException(ReturnQuantityExceedsAvailableException::class);

        app(ReturnPurchaseItems::class)->handle(
            $purchase,
            [new PurchaseReturnLine($purchase->items->first()->id, '10.0001')],
            $this->admin->id,
        );
    }

    public function test_returning_against_a_cancelled_purchase_is_refused(): void
    {
        $purchase = app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '10.0000', '20.00')],
            payments: [],
        );
        app(CancelPurchase::class)->handle($purchase, $this->admin->id);

        $this->expectException(InvalidPurchaseStateException::class);

        app(ReturnPurchaseItems::class)->handle(
            $purchase->fresh(),
            [new PurchaseReturnLine($purchase->items->first()->id, '5.0000')],
            $this->admin->id,
        );
    }

    // --- Tenant isolation -------------------------------------------------

    public function test_tenant_a_cannot_see_tenant_bs_suppliers(): void
    {
        $secondTenantDbPath = storage_path('framework/testing/tenants-'.uniqid());
        config(['tenancy.tenant_database_path' => $secondTenantDbPath]);

        $tenantB = Tenant::create(['name' => 'Karachi Cloth Co', 'slug' => 'karachicloth', 'database' => 'karachicloth', 'status' => 'active']);
        File::ensureDirectoryExists($secondTenantDbPath);
        File::put($secondTenantDbPath.'/karachicloth.sqlite', '');
        app(TenantConnectionFactory::class)->useConnectionFor($tenantB);
        config(['database.default' => 'tenant']);
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--realpath' => false, '--force' => true]);
        app(TenantContext::class)->set($tenantB);

        $this->assertSame(0, Supplier::count());

        File::deleteDirectory($secondTenantDbPath);
    }
}
