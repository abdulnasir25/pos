<?php

namespace Tests\Feature\Suppliers;

use App\Models\User;
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
use App\Modules\Suppliers\Enums\SupplierLedgerEntryType;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Models\SupplierLedgerEntry;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SupplierLedgerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private Unit $meter;
    private Warehouse $warehouse;
    private Product $product;
    private User $admin;
    private PaymentMethod $cash;
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
        $this->supplier = Supplier::create(['name' => 'Faisalabad Textiles', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    private function assertLedgerReconciles(): void
    {
        $sum = SupplierLedgerEntry::where('supplier_id', $this->supplier->id)->sum('amount');
        $this->assertSame(
            (string) $this->supplier->fresh()->balance,
            bcadd('0', (string) $sum, 2),
        );
    }

    public function test_a_fully_paid_purchase_writes_a_charge_and_a_payment_that_net_to_zero(): void
    {
        app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '100.0000', '20.00')],
            payments: [new PurchasePaymentAllocation($this->cash->id, '2000.00')],
        );

        $entries = SupplierLedgerEntry::where('supplier_id', $this->supplier->id)->get();
        $this->assertCount(2, $entries);
        $this->assertTrue($entries->contains(fn ($e) => $e->entry_type === SupplierLedgerEntryType::PurchaseCharge && $e->amount === '2000.00'));
        $this->assertTrue($entries->contains(fn ($e) => $e->entry_type === SupplierLedgerEntryType::Payment && $e->amount === '-2000.00'));
        $this->assertSame('0.00', $this->supplier->fresh()->balance);
        $this->assertLedgerReconciles();
    }

    public function test_an_unpaid_purchase_leaves_a_net_charge(): void
    {
        app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '50.0000', '20.00')],
            payments: [],
        );

        $this->assertSame('1000.00', $this->supplier->fresh()->balance);
        $this->assertLedgerReconciles();
    }

    public function test_cancelling_a_purchase_writes_a_reversing_adjustment(): void
    {
        $purchase = app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->admin->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '100.0000', '20.00')],
            payments: [new PurchasePaymentAllocation($this->cash->id, '1000.00')],
        );

        app(CancelPurchase::class)->handle($purchase, $this->admin->id);

        $this->assertSame('0.00', $this->supplier->fresh()->balance);
        $this->assertLedgerReconciles();

        $adjustment = SupplierLedgerEntry::where('supplier_id', $this->supplier->id)
            ->where('entry_type', SupplierLedgerEntryType::Adjustment)
            ->first();
        $this->assertSame('-1000.00', $adjustment->amount);
    }

    public function test_a_return_writes_a_return_credit_entry(): void
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
            [new PurchaseReturnLine($purchase->items->first()->id, '40.0000')],
            $this->admin->id,
        );

        $credit = SupplierLedgerEntry::where('supplier_id', $this->supplier->id)
            ->where('entry_type', SupplierLedgerEntryType::ReturnCredit)
            ->first();
        $this->assertSame('-800.00', $credit->amount);
        $this->assertLedgerReconciles();
    }
}
