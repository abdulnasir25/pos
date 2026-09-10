<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Inventory\Actions\RecordOpeningStock;
use App\Modules\Inventory\Support\StockLevelService;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InventoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private string $baseUrl;
    private Tenant $tenant;
    private Unit $meter;
    private Warehouse $warehouse;
    private Product $product;
    private User $managerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = storage_path('framework/testing/tenants-'.uniqid());
        config(['tenancy.tenant_database_path' => $this->tenantDbPath]);

        $this->tenant = Tenant::create(['name' => 'Al-Fateh Cloth House', 'slug' => 'alfateh', 'database' => 'alfateh', 'status' => 'active']);
        $this->baseUrl = 'http://alfateh.pos.test';

        File::ensureDirectoryExists($this->tenantDbPath);
        File::put($this->tenantDbPath.'/alfateh.sqlite', '');

        app(TenantConnectionFactory::class)->useConnectionFor($this->tenant);
        config(['database.default' => 'tenant']);

        Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--realpath' => false, '--force' => true]);

        app(TenantContext::class)->set($this->tenant);

        $this->meter = Unit::create(['name' => 'Meter']);
        $this->warehouse = Warehouse::create(['name' => 'Main Store']);
        $this->product = Product::create(['base_unit_id' => $this->meter->id, 'name' => 'Cotton', 'status' => 'active']);
        app(RecordOpeningStock::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '50.0000', '20.0000');

        $this->managerUser = User::create(['name' => 'Manager', 'email' => 'manager@alfateh.test', 'password' => bcrypt('secret')]);
        $managerRole = Role::where('slug', 'manager')->first();
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'inventory.view')->first());
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'inventory.adjust')->first());
        app(AssignRoleToUser::class)->handle($this->managerUser, $managerRole);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    private function resumeTenantContext(): void
    {
        app(TenantContext::class)->set($this->tenant);
        config(['database.default' => 'tenant']);
    }

    private function login(): void
    {
        $this->post("{$this->baseUrl}/login", ['email' => 'manager@alfateh.test', 'password' => 'secret']);
    }

    public function test_a_guest_is_redirected_away_from_the_inventory_page(): void
    {
        $this->get("{$this->baseUrl}/inventory")->assertRedirect('/login');
    }

    public function test_a_user_without_inventory_view_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $this->actingAs($noPermUser)->get("{$this->baseUrl}/inventory")->assertForbidden();
    }

    public function test_the_page_shows_products_warehouses_and_stock(): void
    {
        $this->login();

        $response = $this->get("{$this->baseUrl}/inventory");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Inventory/Index')
            ->has('products', 1)
            ->where('products.0.name', 'Cotton')
            ->where('products.0.stock_by_warehouse.'.$this->warehouse->id, '50.0000')
        );
    }

    public function test_found_stock_increases_the_quantity_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/inventory/adjustments", [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'unit_id' => $this->meter->id,
            'reason' => 'found',
            'quantity' => 5,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->resumeTenantContext();
        $this->assertSame('55.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
    }

    public function test_shrinkage_decreases_the_quantity_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/inventory/adjustments", [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'unit_id' => $this->meter->id,
            'reason' => 'shrinkage',
            'quantity' => 8,
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('42.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
    }

    public function test_damage_decreases_the_quantity_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/inventory/adjustments", [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'unit_id' => $this->meter->id,
            'reason' => 'damage',
            'quantity' => 3,
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('47.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
    }

    public function test_shrinkage_past_available_stock_shows_an_error_not_a_crash(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/inventory/adjustments", [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'unit_id' => $this->meter->id,
            'reason' => 'shrinkage',
            'quantity' => 100,
        ]);

        $response->assertSessionHasErrors('adjustment');
        $this->resumeTenantContext();
        $this->assertSame('50.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
    }

    public function test_a_user_without_inventory_adjust_permission_cannot_record_an_adjustment(): void
    {
        $viewOnlyUser = User::create(['name' => 'ViewOnly', 'email' => 'viewonly@alfateh.test', 'password' => bcrypt('secret')]);
        $cashierRole = Role::where('slug', 'cashier')->first();
        app(GrantPermissionToRole::class)->handle($cashierRole, Permission::where('slug', 'inventory.view')->first());
        app(AssignRoleToUser::class)->handle($viewOnlyUser, $cashierRole);

        $response = $this->actingAs($viewOnlyUser)->post("{$this->baseUrl}/inventory/adjustments", [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'unit_id' => $this->meter->id,
            'reason' => 'found',
            'quantity' => 5,
        ]);

        $response->assertForbidden();
    }
}
