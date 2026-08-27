<?php

namespace Tests\Feature\Inventory;

use App\Modules\Inventory\Actions\RecalculateStockLevel;
use App\Modules\Inventory\Actions\RecordAdjustment;
use App\Modules\Inventory\Actions\RecordDamage;
use App\Modules\Inventory\Actions\RecordOpeningStock;
use App\Modules\Inventory\Actions\RecordPurchaseReturn;
use App\Modules\Inventory\Actions\RecordPurchaseStockIn;
use App\Modules\Inventory\Actions\RecordSaleReturn;
use App\Modules\Inventory\Actions\RecordSaleStockOut;
use App\Modules\Inventory\Actions\RecordTransfer;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Exceptions\InvalidUnitConversionException;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Support\StockLevelService;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Products\Models\UnitConversion;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InventoryMovementTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private Unit $meter;
    private Unit $roll;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = storage_path('framework/testing/tenants-'.uniqid());
        config(['tenancy.tenant_database_path' => $this->tenantDbPath]);

        $tenant = Tenant::create([
            'name' => 'Al-Fateh Cloth House',
            'slug' => 'alfateh',
            'database' => 'alfateh',
            'status' => 'active',
        ]);

        File::ensureDirectoryExists($this->tenantDbPath);
        File::put($this->tenantDbPath.'/alfateh.sqlite', '');

        $connections = app(TenantConnectionFactory::class);
        $connections->useConnectionFor($tenant);
        config(['database.default' => 'tenant']);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);

        app(TenantContext::class)->set($tenant);

        $this->meter = Unit::create(['name' => 'Meter', 'abbreviation' => 'm']);
        $this->roll = Unit::create(['name' => 'Roll', 'abbreviation' => 'rl']);
        $this->warehouse = Warehouse::create(['name' => 'Main Store']);
        $this->product = Product::create([
            'base_unit_id' => $this->meter->id,
            'name' => 'Egyptian Cotton',
            'status' => 'active',
        ]);
        UnitConversion::create([
            'product_id' => $this->product->id,
            'unit_id' => $this->roll->id,
            'factor' => '50.0000', // 1 Roll = 50 Meter
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();

        // RefreshDatabase re-reads config('database.default') at teardown
        // time to decide which connection to roll back — since this test
        // (unlike a real request) never passes through IdentifyTenant's
        // terminate() hook, nothing else resets it back to landlord.
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    public function test_opening_stock_sets_quantity_and_average_cost(): void
    {
        app(RecordOpeningStock::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '100.0000', '20.0000'
        );

        $level = StockLevel::first();
        $this->assertSame('100.0000', $level->quantity_base_unit);
        $this->assertSame('20.0000', $level->average_cost);
    }

    public function test_unit_conversion_from_roll_to_meter(): void
    {
        app(RecordOpeningStock::class)->handle(
            $this->product, $this->warehouse->id, $this->roll->id, '2.0000', '900.0000'
        );

        // 2 Roll * 50 Meter/Roll = 100 Meter
        $this->assertSame('100.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
    }

    public function test_unknown_unit_without_a_conversion_throws(): void
    {
        $strangeUnit = Unit::create(['name' => 'Bale']);

        $this->expectException(InvalidUnitConversionException::class);

        app(RecordOpeningStock::class)->handle(
            $this->product, $this->warehouse->id, $strangeUnit->id, '1.0000', '100.0000'
        );
    }

    public function test_purchase_stock_in_recomputes_weighted_average_cost(): void
    {
        app(RecordOpeningStock::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '100.0000', '20.0000'
        );

        // 100 @ 20 existing, + 100 @ 30 incoming = 200 @ weighted average 25
        app(RecordPurchaseStockIn::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '100.0000', '30.0000', 'FakePurchase', 1
        );

        $level = StockLevel::first();
        $this->assertSame('200.0000', $level->quantity_base_unit);
        $this->assertSame('25.0000', $level->average_cost);
    }

    public function test_sale_stock_out_reduces_quantity_and_freezes_cost_snapshot(): void
    {
        app(RecordOpeningStock::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '100.0000', '20.0000'
        );

        $movement = app(RecordSaleStockOut::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '30.0000', 'FakeSale', 1
        );

        $this->assertSame('-30.0000', $movement->quantity_base_unit);
        $this->assertSame('20.0000', $movement->unit_cost);
        $this->assertSame('70.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
    }

    public function test_sale_stock_out_fails_closed_when_stock_is_insufficient(): void
    {
        app(RecordOpeningStock::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '10.0000', '20.0000'
        );

        $this->expectException(InsufficientStockException::class);

        app(RecordSaleStockOut::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '11.0000', 'FakeSale', 1
        );
    }

    public function test_a_failed_sale_leaves_stock_completely_unchanged(): void
    {
        app(RecordOpeningStock::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '10.0000', '20.0000'
        );

        try {
            app(RecordSaleStockOut::class)->handle(
                $this->product, $this->warehouse->id, $this->meter->id, '11.0000', 'FakeSale', 1
            );
        } catch (InsufficientStockException) {
            // expected
        }

        $this->assertSame('10.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
    }

    public function test_sale_return_adds_stock_back_at_the_existing_average_cost(): void
    {
        app(RecordOpeningStock::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '100.0000', '20.0000'
        );
        app(RecordSaleStockOut::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '30.0000', 'FakeSale', 1
        );

        app(RecordSaleReturn::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '10.0000', 'FakeSaleReturn', 1
        );

        $level = StockLevel::first();
        $this->assertSame('80.0000', $level->quantity_base_unit);
        $this->assertSame('20.0000', $level->average_cost); // unchanged by a return
    }

    public function test_purchase_return_decreases_stock_and_cannot_exceed_what_is_on_hand(): void
    {
        app(RecordOpeningStock::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '20.0000', '20.0000'
        );

        app(RecordPurchaseReturn::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '5.0000', 'FakePurchase', 1
        );
        $this->assertSame('15.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));

        $this->expectException(InsufficientStockException::class);
        app(RecordPurchaseReturn::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '100.0000', 'FakePurchase', 1
        );
    }

    public function test_adjustment_supports_both_directions(): void
    {
        app(RecordOpeningStock::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '50.0000', '20.0000'
        );

        app(RecordAdjustment::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '5.0000');
        $this->assertSame('55.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));

        app(RecordAdjustment::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '-8.0000');
        $this->assertSame('47.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
    }

    public function test_negative_adjustment_cannot_take_stock_below_zero(): void
    {
        app(RecordOpeningStock::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '5.0000', '20.0000'
        );

        $this->expectException(InsufficientStockException::class);
        app(RecordAdjustment::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '-6.0000');
    }

    public function test_damage_reduces_stock_and_respects_the_floor(): void
    {
        app(RecordOpeningStock::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '10.0000', '20.0000'
        );

        app(RecordDamage::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '3.0000');
        $this->assertSame('7.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));

        $this->expectException(InsufficientStockException::class);
        app(RecordDamage::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '100.0000');
    }

    public function test_transfer_moves_stock_between_warehouses_atomically(): void
    {
        $secondWarehouse = Warehouse::create(['name' => 'Back Store']);

        app(RecordOpeningStock::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '50.0000', '20.0000'
        );

        app(RecordTransfer::class)->handle(
            $this->product, $this->warehouse->id, $secondWarehouse->id, $this->meter->id, '20.0000'
        );

        $this->assertSame('30.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
        $this->assertSame('20.0000', app(StockLevelService::class)->currentStock($this->product->id, $secondWarehouse->id));
    }

    public function test_a_transfer_that_would_overdraw_the_source_moves_nothing(): void
    {
        $secondWarehouse = Warehouse::create(['name' => 'Back Store']);

        app(RecordOpeningStock::class)->handle(
            $this->product, $this->warehouse->id, $this->meter->id, '5.0000', '20.0000'
        );

        try {
            app(RecordTransfer::class)->handle(
                $this->product, $this->warehouse->id, $secondWarehouse->id, $this->meter->id, '10.0000'
            );
        } catch (InsufficientStockException) {
            // expected
        }

        $this->assertSame('5.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
        $this->assertSame('0.0000', app(StockLevelService::class)->currentStock($this->product->id, $secondWarehouse->id));
    }

    public function test_the_ledger_and_the_stock_level_cache_always_agree(): void
    {
        app(RecordOpeningStock::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '100.0000', '20.0000');
        app(RecordPurchaseStockIn::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '50.0000', '25.0000', 'FakePurchase', 1);
        app(RecordSaleStockOut::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '30.0000', 'FakeSale', 1);
        app(RecordDamage::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '5.0000');

        $service = app(StockLevelService::class);
        $this->assertSame(
            $service->currentStockFromLedger($this->product->id, $this->warehouse->id),
            $service->currentStock($this->product->id, $this->warehouse->id),
        );
    }

    public function test_the_cache_can_be_thrown_away_and_rebuilt_from_the_ledger_alone(): void
    {
        app(RecordOpeningStock::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '100.0000', '20.0000');
        app(RecordSaleStockOut::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '30.0000', 'FakeSale', 1);

        StockLevel::query()->update(['quantity_base_unit' => '999999.0000']); // simulate a corrupted cache

        app(RecalculateStockLevel::class)->handle($this->product->id, $this->warehouse->id);

        $this->assertSame('70.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
    }
}
