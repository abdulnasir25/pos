<?php

namespace Tests\Feature\Products;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Products\Models\UnitConversion;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductsControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private string $baseUrl;
    private Tenant $tenant;
    private User $managerUser;
    private Unit $meter;

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

        $this->managerUser = User::create(['name' => 'Manager', 'email' => 'manager@alfateh.test', 'password' => bcrypt('secret')]);
        $managerRole = Role::where('slug', 'manager')->first();
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'products.manage')->first());
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

    public function test_the_products_manage_permission_exists_in_the_baseline_seed(): void
    {
        $this->assertNotNull(Permission::where('slug', 'products.manage')->first());
    }

    public function test_a_user_without_products_manage_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->get("{$this->baseUrl}/products");

        $response->assertForbidden();
    }

    public function test_the_page_lists_units_and_products(): void
    {
        Product::create(['base_unit_id' => $this->meter->id, 'name' => 'Cotton', 'status' => 'active']);
        $this->login();

        $response = $this->get("{$this->baseUrl}/products");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Products/Index')
            ->has('units', 1)
            ->has('products', 1)
        );
    }

    public function test_a_unit_can_be_added_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/products/units", ['name' => 'Yard', 'abbreviation' => 'yd']);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, Unit::where('name', 'Yard')->count());
    }

    public function test_a_product_can_be_added_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/products", [
            'base_unit_id' => $this->meter->id,
            'name' => 'Cotton',
            'sku' => 'CT-001',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('active', Product::where('sku', 'CT-001')->first()->status);
    }

    public function test_a_duplicate_sku_shows_an_error_not_a_crash(): void
    {
        Product::create(['base_unit_id' => $this->meter->id, 'name' => 'Cotton', 'sku' => 'CT-001', 'status' => 'active']);
        $this->login();

        $response = $this->post("{$this->baseUrl}/products", [
            'base_unit_id' => $this->meter->id,
            'name' => 'Cotton Again',
            'sku' => 'CT-001',
        ]);

        $response->assertSessionHasErrors('sku');
    }

    public function test_an_alternate_unit_can_be_added_to_a_product(): void
    {
        $yard = Unit::create(['name' => 'Yard']);
        $product = Product::create(['base_unit_id' => $this->meter->id, 'name' => 'Cotton', 'status' => 'active']);
        $this->login();

        $response = $this->post("{$this->baseUrl}/products/{$product->id}/conversions", [
            'unit_id' => $yard->id,
            'factor' => '0.9144',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, UnitConversion::where('product_id', $product->id)->count());
    }

    public function test_adding_the_products_own_base_unit_as_a_conversion_shows_an_error_not_a_crash(): void
    {
        $product = Product::create(['base_unit_id' => $this->meter->id, 'name' => 'Cotton', 'status' => 'active']);
        $this->login();

        $response = $this->post("{$this->baseUrl}/products/{$product->id}/conversions", [
            'unit_id' => $this->meter->id,
            'factor' => '1.0000',
        ]);

        $response->assertSessionHasErrors('conversion');
    }

    public function test_a_product_can_be_updated_through_the_form(): void
    {
        $product = Product::create(['base_unit_id' => $this->meter->id, 'name' => 'Cotton', 'sku' => 'CT-001', 'status' => 'active']);
        $this->login();

        $response = $this->post("{$this->baseUrl}/products/{$product->id}", [
            'name' => 'Cotton Fabric',
            'sku' => 'CT-002',
            'low_stock_threshold' => '10',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $fresh = $product->fresh();
        $this->assertSame('Cotton Fabric', $fresh->name);
        $this->assertSame('CT-002', $fresh->sku);
    }

    public function test_a_product_can_be_deactivated_and_reactivated(): void
    {
        $product = Product::create(['base_unit_id' => $this->meter->id, 'name' => 'Cotton', 'status' => 'active']);
        $this->login();

        $this->post("{$this->baseUrl}/products/{$product->id}/toggle-status")->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('inactive', $product->fresh()->status);

        $this->login();
        $this->post("{$this->baseUrl}/products/{$product->id}/toggle-status")->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('active', $product->fresh()->status);
    }

    public function test_a_unit_can_be_renamed_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/products/units/{$this->meter->id}", [
            'name' => 'Metre',
            'abbreviation' => 'm',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('Metre', $this->meter->fresh()->name);
    }
}
