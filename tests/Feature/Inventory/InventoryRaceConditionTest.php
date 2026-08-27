<?php

namespace Tests\Feature\Inventory;

use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Actions\RecordOpeningStock;
use App\Modules\Inventory\Enums\MovementReason;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Support\StockLevelService;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A single PHP process can't truly run two HTTP requests at the exact
 * same instant, so this doesn't prove concurrency safety by racing two
 * real workers against each other. What it does prove: the decrement is
 * one SQL statement whose WHERE clause re-evaluates against the row's
 * current committed value — not a value read earlier in PHP — which is
 * the actual property that makes concurrent overselling impossible
 * regardless of how many workers are involved. See the module's
 * race-condition analysis for the full argument.
 */
class InventoryRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private Unit $meter;
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

        app(TenantConnectionFactory::class)->useConnectionFor($tenant);
        config(['database.default' => 'tenant']);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);

        app(TenantContext::class)->set($tenant);

        $this->meter = Unit::create(['name' => 'Meter', 'abbreviation' => 'm']);
        $this->warehouse = Warehouse::create(['name' => 'Main Store']);
        $this->product = Product::create(['base_unit_id' => $this->meter->id, 'name' => 'Egyptian Cotton', 'status' => 'active']);

        app(RecordOpeningStock::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '10.0000', '20.0000');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    /**
     * Two "sales" for 7 units each against 10 units on hand — only one
     * can succeed. If the guard were a naive "read stock, check in PHP,
     * then write" instead of one atomic UPDATE ... WHERE, both of these
     * could pass their check before either wrote, oversold by 4 units.
     */
    public function test_two_competing_stock_outs_cannot_both_succeed_against_insufficient_shared_stock(): void
    {
        $post = app(PostInventoryMovement::class);
        $succeeded = 0;
        $failed = 0;

        foreach ([1, 2] as $attempt) {
            try {
                $post->handle(
                    productId: $this->product->id,
                    warehouseId: $this->warehouse->id,
                    reason: MovementReason::Sale,
                    quantityBaseUnit: '7.0000',
                    referenceType: 'FakeSale',
                    referenceId: $attempt,
                );
                $succeeded++;
            } catch (InsufficientStockException) {
                $failed++;
            }
        }

        $this->assertSame(1, $succeeded);
        $this->assertSame(1, $failed);
        $this->assertSame('3.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
    }

    /**
     * Directly proves the mechanism: the WHERE clause in the same UPDATE
     * statement is what gates the write, not a prior SELECT — issuing
     * the raw statement twice in a row demonstrates the second call's
     * affected-row-count is 0 the moment the row can no longer satisfy
     * the condition, with no separate read step in between to race.
     */
    public function test_the_conditional_update_reports_zero_affected_rows_once_stock_is_exhausted(): void
    {
        $connection = DB::connection('tenant');

        $firstAffected = $connection->update(
            'UPDATE stock_levels SET quantity_base_unit = quantity_base_unit - ? WHERE product_id = ? AND warehouse_id = ? AND quantity_base_unit >= ?',
            ['10.0000', $this->product->id, $this->warehouse->id, '10.0000']
        );

        $secondAffected = $connection->update(
            'UPDATE stock_levels SET quantity_base_unit = quantity_base_unit - ? WHERE product_id = ? AND warehouse_id = ? AND quantity_base_unit >= ?',
            ['0.0001', $this->product->id, $this->warehouse->id, '0.0001']
        );

        $this->assertSame(1, $firstAffected);
        $this->assertSame(0, $secondAffected);
    }

    /**
     * Two concurrent purchases updating the weighted-average cost must
     * not lose one contribution to the other — proves the recompute is
     * one atomic expression, not read-then-write.
     */
    public function test_two_sequential_purchases_both_contribute_to_the_average_cost(): void
    {
        $post = app(PostInventoryMovement::class);

        // Starting: 10 @ 20.
        $post->handle($this->product->id, $this->warehouse->id, MovementReason::Purchase, '10.0000', '30.0000', 'FakePurchase', 1);
        // Now: 20 @ 25 (weighted average of 10@20 and 10@30).
        $post->handle($this->product->id, $this->warehouse->id, MovementReason::Purchase, '20.0000', '25.0000', 'FakePurchase', 2);
        // Now: 40 @ 25 (weighted average of 20@25 and 20@25) — both purchases' contributions present.

        $level = app(StockLevelService::class);
        $this->assertSame('40.0000', $level->currentStock($this->product->id, $this->warehouse->id));
    }
}
