<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Inventory\Actions\RecordOpeningStock;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Sales\Models\Sale;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PosControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private string $baseUrl;
    private Tenant $tenant;
    private Unit $meter;
    private Warehouse $warehouse;
    private Product $product;
    private User $cashierUser;
    private PaymentMethod $cash;

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
        $this->cash = PaymentMethod::create(['name' => 'Cash']);

        app(RecordOpeningStock::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '100.0000', '20.0000');

        $this->cashierUser = User::create(['name' => 'Cashier', 'email' => 'cashier@alfateh.test', 'password' => bcrypt('secret')]);
        $cashierRole = Role::where('slug', 'cashier')->first();
        app(GrantPermissionToRole::class)->handle($cashierRole, Permission::where('slug', 'sales.create')->first());
        app(AssignRoleToUser::class)->handle($this->cashierUser, $cashierRole);
    }

    /**
     * IdentifyTenant::terminate() runs after every test HTTP request
     * (Laravel's test client calls $kernel->terminate()), resetting the
     * connection to landlord and clearing TenantContext. Re-establish
     * both before any Eloquent assertion that follows an HTTP call.
     */
    private function resumeTenantContext(): void
    {
        app(TenantContext::class)->set($this->tenant);
        config(['database.default' => 'tenant']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    public function test_a_guest_is_redirected_away_from_the_pos_screen(): void
    {
        $response = $this->get("{$this->baseUrl}/pos");

        $response->assertRedirect('/login');
    }

    public function test_a_user_without_the_sales_create_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->get("{$this->baseUrl}/pos");

        $response->assertForbidden();
    }

    public function test_a_cashier_can_view_the_pos_screen_with_products_and_stock(): void
    {
        $response = $this->post("{$this->baseUrl}/login", [
            'email' => 'cashier@alfateh.test',
            'password' => 'secret',
        ]);
        $response->assertRedirect('/dashboard');

        $response = $this->get("{$this->baseUrl}/pos");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            ->has('products', 1)
            ->where('products.0.name', 'Cotton')
            ->where('products.0.stock_by_warehouse.'.$this->warehouse->id, '100.0000')
        );
    }

    public function test_confirming_a_sale_through_the_pos_screen_creates_it_and_decrements_stock(): void
    {
        $this->post("{$this->baseUrl}/login", ['email' => 'cashier@alfateh.test', 'password' => 'secret']);

        $response = $this->post("{$this->baseUrl}/pos/sale", [
            'customer_id' => null,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['product_id' => $this->product->id, 'unit_id' => $this->meter->id, 'quantity' => 5, 'unit_price' => 50, 'discount' => 0],
            ],
            'payments' => [
                ['payment_method_id' => $this->cash->id, 'amount' => 250],
            ],
        ]);

        $response->assertRedirect('/pos');
        $this->resumeTenantContext();
        $this->assertSame(1, Sale::count());
        $this->assertSame('250.00', Sale::first()->total);
    }

    public function test_an_overpayment_through_the_pos_screen_is_rejected_gracefully(): void
    {
        $this->post("{$this->baseUrl}/login", ['email' => 'cashier@alfateh.test', 'password' => 'secret']);

        $response = $this->post("{$this->baseUrl}/pos/sale", [
            'customer_id' => null,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['product_id' => $this->product->id, 'unit_id' => $this->meter->id, 'quantity' => 5, 'unit_price' => 50, 'discount' => 0],
            ],
            'payments' => [
                ['payment_method_id' => $this->cash->id, 'amount' => 999],
            ],
        ]);

        $response->assertSessionHasErrors('sale');
        $this->resumeTenantContext();
        $this->assertSame(0, Sale::count());
    }

    public function test_a_line_exceeding_available_stock_is_rejected_gracefully(): void
    {
        $this->post("{$this->baseUrl}/login", ['email' => 'cashier@alfateh.test', 'password' => 'secret']);

        $response = $this->post("{$this->baseUrl}/pos/sale", [
            'customer_id' => null,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['product_id' => $this->product->id, 'unit_id' => $this->meter->id, 'quantity' => 500, 'unit_price' => 50, 'discount' => 0],
            ],
            'payments' => [
                ['payment_method_id' => $this->cash->id, 'amount' => 25000],
            ],
        ]);

        $response->assertSessionHasErrors('sale');
        $this->resumeTenantContext();
        $this->assertSame(0, Sale::count());
    }
}
