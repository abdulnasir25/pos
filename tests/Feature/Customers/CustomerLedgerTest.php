<?php

namespace Tests\Feature\Customers;

use App\Models\User;
use App\Modules\Customers\Actions\RecordCustomerPayment;
use App\Modules\Customers\Enums\CustomerLedgerEntryType;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerLedgerEntry;
use App\Modules\Inventory\Actions\RecordOpeningStock;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Sales\Actions\CancelSale;
use App\Modules\Sales\Actions\ConfirmSale;
use App\Modules\Sales\Actions\ReturnSaleItems;
use App\Modules\Sales\DTOs\CartLine;
use App\Modules\Sales\DTOs\PaymentAllocation;
use App\Modules\Sales\DTOs\ReturnLine;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CustomerLedgerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private Unit $meter;
    private Warehouse $warehouse;
    private Product $product;
    private User $cashier;
    private PaymentMethod $cash;
    private Customer $customer;

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
        $this->customer = Customer::create(['name' => 'Bilal', 'status' => 'active']);

        app(RecordOpeningStock::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '1000.0000', '20.0000');
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
        $sum = CustomerLedgerEntry::where('customer_id', $this->customer->id)->sum('amount');
        $this->assertSame(
            (string) $this->customer->fresh()->balance,
            bcadd('0', (string) $sum, 2),
        );
    }

    public function test_a_walk_in_sale_writes_no_ledger_entries(): void
    {
        app(ConfirmSale::class)->handle(
            customerId: null,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, '5.0000', '50.00')],
            payments: [new PaymentAllocation($this->cash->id, '250.00')],
        );

        $this->assertSame(0, CustomerLedgerEntry::count());
    }

    public function test_a_fully_paid_registered_sale_writes_a_charge_and_a_payment_that_net_to_zero(): void
    {
        app(ConfirmSale::class)->handle(
            customerId: $this->customer->id,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, '5.0000', '50.00')],
            payments: [new PaymentAllocation($this->cash->id, '250.00')],
        );

        $entries = CustomerLedgerEntry::where('customer_id', $this->customer->id)->get();
        $this->assertCount(2, $entries);
        $this->assertTrue($entries->contains(fn ($e) => $e->entry_type === CustomerLedgerEntryType::SaleCharge && $e->amount === '250.00'));
        $this->assertTrue($entries->contains(fn ($e) => $e->entry_type === CustomerLedgerEntryType::Payment && $e->amount === '-250.00'));
        $this->assertSame('0.00', $this->customer->fresh()->balance);
        $this->assertLedgerReconciles();
    }

    public function test_a_partially_paid_sale_leaves_a_net_charge(): void
    {
        app(ConfirmSale::class)->handle(
            customerId: $this->customer->id,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, '5.0000', '50.00')],
            payments: [new PaymentAllocation($this->cash->id, '100.00')],
        );

        $this->assertSame('150.00', $this->customer->fresh()->balance);
        $this->assertLedgerReconciles();
    }

    public function test_cancelling_a_sale_writes_a_reversing_adjustment(): void
    {
        $sale = app(ConfirmSale::class)->handle(
            customerId: $this->customer->id,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, '5.0000', '50.00')],
            payments: [new PaymentAllocation($this->cash->id, '100.00')],
        );

        app(CancelSale::class)->handle($sale, $this->cashier->id);

        $this->assertSame('0.00', $this->customer->fresh()->balance);
        $this->assertLedgerReconciles();

        $adjustment = CustomerLedgerEntry::where('customer_id', $this->customer->id)
            ->where('entry_type', CustomerLedgerEntryType::Adjustment)
            ->first();
        $this->assertSame('-150.00', $adjustment->amount);
    }

    public function test_a_return_writes_a_return_credit_entry(): void
    {
        $sale = app(ConfirmSale::class)->handle(
            customerId: $this->customer->id,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.00')],
            payments: [new PaymentAllocation($this->cash->id, '500.00')],
        );

        app(ReturnSaleItems::class)->handle(
            $sale,
            [new ReturnLine($sale->items->first()->id, '4.0000')],
            $this->cashier->id,
        );

        $credit = CustomerLedgerEntry::where('customer_id', $this->customer->id)
            ->where('entry_type', CustomerLedgerEntryType::ReturnCredit)
            ->first();
        $this->assertSame('-200.00', $credit->amount);
        $this->assertLedgerReconciles();
    }

    public function test_a_standalone_customer_payment_reduces_balance_and_writes_a_ledger_entry(): void
    {
        app(ConfirmSale::class)->handle(
            customerId: $this->customer->id,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: null,
            lines: [new CartLine($this->product, $this->meter->id, '5.0000', '50.00')],
            payments: [],
        );
        $this->assertSame('250.00', $this->customer->fresh()->balance);

        app(RecordCustomerPayment::class)->handle($this->customer, '100.00', $this->cash->id, $this->cashier->id);

        $this->assertSame('150.00', $this->customer->fresh()->balance);
        $this->assertLedgerReconciles();
    }
}
