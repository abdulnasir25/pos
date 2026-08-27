<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Actions\RecordOpeningStock;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Support\StockLevelService;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Products\Models\UnitConversion;
use App\Modules\Sales\Actions\CancelSale;
use App\Modules\Sales\Actions\ConfirmSale;
use App\Modules\Sales\Actions\ReturnSaleItems;
use App\Modules\Sales\DTOs\CartLine;
use App\Modules\Sales\DTOs\PaymentAllocation;
use App\Modules\Sales\DTOs\ReturnLine;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Exceptions\OverpaymentException;
use App\Modules\Sales\Exceptions\ReturnQuantityExceedsAvailableException;
use App\Modules\Sales\Exceptions\WalkInCreditNotAllowedException;
use App\Modules\Sales\Support\ReceiptBuilder;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SalesModuleTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private Unit $meter;
    private Unit $roll;
    private Warehouse $warehouse;
    private Product $product;
    private User $cashier;
    private PaymentMethod $cash;
    private PaymentMethod $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = storage_path('framework/testing/tenants-'.uniqid());
        config(['tenancy.tenant_database_path' => $this->tenantDbPath]);

        $tenant = Tenant::create([
            'name' => 'Al-Fateh Cloth House', 'slug' => 'alfateh', 'database' => 'alfateh', 'status' => 'active',
        ]);

        File::ensureDirectoryExists($this->tenantDbPath);
        File::put($this->tenantDbPath.'/alfateh.sqlite', '');

        app(TenantConnectionFactory::class)->useConnectionFor($tenant);
        config(['database.default' => 'tenant']);

        Artisan::call('migrate', [
            '--database' => 'tenant', '--path' => 'database/migrations/tenant', '--realpath' => false, '--force' => true,
        ]);

        app(TenantContext::class)->set($tenant);

        $this->meter = Unit::create(['name' => 'Meter', 'abbreviation' => 'm']);
        $this->roll = Unit::create(['name' => 'Roll', 'abbreviation' => 'rl']);
        $this->warehouse = Warehouse::create(['name' => 'Main Store']);
        $this->product = Product::create(['base_unit_id' => $this->meter->id, 'name' => 'Egyptian Cotton', 'status' => 'active']);
        UnitConversion::create(['product_id' => $this->product->id, 'unit_id' => $this->roll->id, 'factor' => '50.0000']);

        $this->cashier = User::create(['name' => 'Cashier One', 'email' => 'cashier@alfateh.test', 'password' => bcrypt('secret')]);
        $this->cash = PaymentMethod::create(['name' => 'Cash']);
        $this->bank = PaymentMethod::create(['name' => 'Bank']);

        app(RecordOpeningStock::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '200.0000', '20.0000');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    private function confirmSale(array $lines, array $payments, ?int $customerId = null): \App\Modules\Sales\Models\Sale
    {
        return app(ConfirmSale::class)->handle(
            customerId: $customerId,
            warehouseId: $this->warehouse->id,
            cashierId: $this->cashier->id,
            salesEmployeeId: null,
            lines: $lines,
            payments: $payments,
        );
    }

    public function test_walk_in_sale_fully_paid_in_cash(): void
    {
        $sale = $this->confirmSale(
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '500.00')],
        );

        $this->assertTrue($sale->isWalkIn());
        $this->assertSame(SaleStatus::Confirmed, $sale->status);
        $this->assertSame('500.00', $sale->total);
        $this->assertSame('500.00', $sale->paid_total);
        $this->assertSame('0.00', $sale->balance_due);
        $this->assertSame('190.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
    }

    public function test_walk_in_sale_with_an_outstanding_balance_is_rejected(): void
    {
        $this->expectException(WalkInCreditNotAllowedException::class);

        $this->confirmSale(
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '300.00')],
        );
    }

    public function test_customer_sale_with_partial_payment_records_an_outstanding_balance(): void
    {
        $customer = Customer::create(['name' => 'Zainab Traders', 'balance' => '0.00']);

        $sale = $this->confirmSale(
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '300.00')],
            customerId: $customer->id,
        );

        $this->assertSame('200.00', $sale->balance_due);
        $this->assertSame('200.00', (string) $customer->fresh()->balance);
    }

    public function test_sale_splits_payment_across_multiple_methods(): void
    {
        $sale = $this->confirmSale(
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '100.0000')],
            payments: [
                new PaymentAllocation($this->cash->id, '600.00'),
                new PaymentAllocation($this->bank->id, '400.00'),
            ],
        );

        $this->assertSame('1000.00', $sale->paid_total);
        $this->assertCount(2, $sale->payments);
    }

    public function test_multiple_products_in_different_units_on_one_sale(): void
    {
        $sale = $this->confirmSale(
            lines: [
                new CartLine($this->product, $this->meter->id, '20.0000', '50.0000'),   // 20 Meter
                new CartLine($this->product, $this->roll->id, '1.0000', '2000.0000'),   // 1 Roll = 50 Meter
            ],
            payments: [new PaymentAllocation($this->cash->id, '3000.00')],
        );

        $this->assertCount(2, $sale->items);
        // 200 opening - 20 - 50 = 130
        $this->assertSame('130.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
    }

    public function test_a_line_level_discount_reduces_the_sale_total(): void
    {
        $sale = $this->confirmSale(
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.0000', discount: '50.00')],
            payments: [new PaymentAllocation($this->cash->id, '450.00')],
        );

        $this->assertSame('500.00', $sale->subtotal);
        $this->assertSame('50.00', $sale->discount_total);
        $this->assertSame('450.00', $sale->total);
    }

    public function test_overpayment_is_rejected(): void
    {
        $this->expectException(OverpaymentException::class);

        $this->confirmSale(
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '600.00')],
        );
    }

    public function test_insufficient_stock_fails_the_whole_sale_and_leaves_stock_untouched(): void
    {
        $this->expectException(InsufficientStockException::class);

        try {
            $this->confirmSale(
                lines: [new CartLine($this->product, $this->meter->id, '9999.0000', '50.0000')],
                payments: [new PaymentAllocation($this->cash->id, '499950.00')],
            );
        } finally {
            $this->assertSame('200.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
        }
    }

    public function test_cogs_snapshot_on_the_sale_item_matches_what_inventory_reported(): void
    {
        $sale = $this->confirmSale(
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '500.00')],
        );

        $this->assertSame('20.0000', (string) $sale->items->first()->unit_cost_snapshot);
    }

    public function test_cancelling_a_confirmed_sale_restores_stock_and_reverses_the_receivable(): void
    {
        $customer = Customer::create(['name' => 'Zainab Traders', 'balance' => '0.00']);

        $sale = $this->confirmSale(
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '300.00')],
            customerId: $customer->id,
        );
        $this->assertSame('200.00', (string) $customer->fresh()->balance);
        $this->assertSame('190.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));

        $cancelled = app(CancelSale::class)->handle($sale, $this->cashier->id);

        $this->assertSame(SaleStatus::Cancelled, $cancelled->status);
        $this->assertSame('200.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
        $this->assertSame('0.00', (string) $customer->fresh()->balance);
    }

    public function test_a_cancelled_sale_cannot_be_cancelled_again(): void
    {
        $sale = $this->confirmSale(
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '500.00')],
        );
        app(CancelSale::class)->handle($sale, $this->cashier->id);

        $this->expectException(\App\Modules\Sales\Exceptions\InvalidSaleStateException::class);
        app(CancelSale::class)->handle($sale->fresh(), $this->cashier->id);
    }

    public function test_partial_return_restores_stock_and_credits_the_customer_proportionally(): void
    {
        $customer = Customer::create(['name' => 'Zainab Traders', 'balance' => '0.00']);

        $sale = $this->confirmSale(
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.0000', discount: '50.00')], // 450 net for 10m
            payments: [new PaymentAllocation($this->cash->id, '450.00')],
            customerId: $customer->id,
        );
        $saleItem = $sale->items->first();

        $return = app(ReturnSaleItems::class)->handle(
            $sale, [new ReturnLine($saleItem->id, '4.0000')], $this->cashier->id
        );

        // 4/10 of the 450 net line total = 180
        $this->assertSame('180.00', (string) $return->refund_amount);
        $this->assertSame('-180.00', (string) $customer->fresh()->balance);
        $this->assertSame('194.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
        $this->assertSame(SaleStatus::Confirmed, $sale->fresh()->status);
    }

    public function test_returning_more_than_was_sold_is_rejected(): void
    {
        $sale = $this->confirmSale(
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '500.00')],
        );
        $saleItem = $sale->items->first();

        $this->expectException(ReturnQuantityExceedsAvailableException::class);
        app(ReturnSaleItems::class)->handle($sale, [new ReturnLine($saleItem->id, '11.0000')], $this->cashier->id);
    }

    public function test_returning_everything_marks_the_sale_refunded(): void
    {
        $sale = $this->confirmSale(
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '500.00')],
        );
        $saleItem = $sale->items->first();

        app(ReturnSaleItems::class)->handle($sale, [new ReturnLine($saleItem->id, '10.0000')], $this->cashier->id);

        $this->assertSame(SaleStatus::Refunded, $sale->fresh()->status);
    }

    public function test_a_second_return_cannot_exceed_what_the_first_return_left_eligible(): void
    {
        $sale = $this->confirmSale(
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '500.00')],
        );
        $saleItem = $sale->items->first();

        app(ReturnSaleItems::class)->handle($sale, [new ReturnLine($saleItem->id, '6.0000')], $this->cashier->id);

        $this->expectException(ReturnQuantityExceedsAvailableException::class);
        app(ReturnSaleItems::class)->handle($sale->fresh(), [new ReturnLine($saleItem->id, '5.0000')], $this->cashier->id);
    }

    public function test_receipt_reflects_the_confirmed_sale(): void
    {
        $sale = $this->confirmSale(
            lines: [new CartLine($this->product, $this->meter->id, '10.0000', '50.0000')],
            payments: [new PaymentAllocation($this->cash->id, '500.00')],
        );

        $receipt = app(ReceiptBuilder::class)->build($sale);

        $this->assertSame($sale->reference_no, $receipt->referenceNo);
        $this->assertSame('Walk-in', $receipt->customerName);
        $this->assertSame('500.00', $receipt->total);
        $this->assertCount(1, $receipt->lines);
        $this->assertSame('Cash', $receipt->payments[0]['method']);
    }
}
