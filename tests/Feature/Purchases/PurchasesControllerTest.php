<?php

namespace Tests\Feature\Purchases;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Purchases\Actions\ConfirmPurchase;
use App\Modules\Purchases\DTOs\PurchaseLine;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PurchasesControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private string $baseUrl;
    private Tenant $tenant;
    private User $managerUser;
    private Unit $meter;
    private Warehouse $warehouse;
    private Product $product;
    private PaymentMethod $cash;
    private Supplier $supplier;

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
        $this->supplier = Supplier::create(['name' => 'Faisalabad Textiles', 'balance' => '0.00', 'status' => 'active']);

        $this->managerUser = User::create(['name' => 'Manager', 'email' => 'manager@alfateh.test', 'password' => bcrypt('secret')]);
        $managerRole = Role::where('slug', 'manager')->first();
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'purchases.manage')->first());
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

    public function test_the_purchases_manage_permission_exists_in_the_baseline_seed(): void
    {
        $this->assertNotNull(Permission::where('slug', 'purchases.manage')->first());
    }

    public function test_a_user_without_purchases_manage_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->get("{$this->baseUrl}/purchases");

        $response->assertForbidden();
    }

    public function test_a_supplier_can_be_added_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/purchases/suppliers", ['name' => 'Karachi Fabrics']);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, Supplier::where('name', 'Karachi Fabrics')->count());
    }

    public function test_a_supplier_can_be_updated_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/purchases/suppliers/{$this->supplier->id}", [
            'name' => 'Faisalabad Textiles Ltd',
            'phone' => '0300-1112222',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $fresh = $this->supplier->fresh();
        $this->assertSame('Faisalabad Textiles Ltd', $fresh->name);
        $this->assertSame('0300-1112222', $fresh->phone);
    }

    public function test_a_supplier_can_be_deactivated_and_reactivated(): void
    {
        $this->login();

        $this->post("{$this->baseUrl}/purchases/suppliers/{$this->supplier->id}/toggle-status")->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('inactive', $this->supplier->fresh()->status);

        $this->login();
        $this->post("{$this->baseUrl}/purchases/suppliers/{$this->supplier->id}/toggle-status")->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('active', $this->supplier->fresh()->status);
    }

    public function test_confirming_a_purchase_through_the_form_increases_stock(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/purchases", [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['product_id' => $this->product->id, 'unit_id' => $this->meter->id, 'quantity' => 100, 'unit_cost' => 20, 'discount' => 0],
            ],
            'payments' => [
                ['payment_method_id' => $this->cash->id, 'amount' => 2000],
            ],
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, Purchase::count());
        $this->assertSame('0.00', $this->supplier->fresh()->balance);
    }

    public function test_an_unpaid_purchase_leaves_a_supplier_balance(): void
    {
        $this->login();

        $this->post("{$this->baseUrl}/purchases", [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['product_id' => $this->product->id, 'unit_id' => $this->meter->id, 'quantity' => 50, 'unit_cost' => 20, 'discount' => 0],
            ],
            'payments' => [],
        ]);

        $this->resumeTenantContext();
        $this->assertSame('1000.00', $this->supplier->fresh()->balance);
    }

    public function test_an_overpayment_shows_an_error_not_a_crash(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/purchases", [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['product_id' => $this->product->id, 'unit_id' => $this->meter->id, 'quantity' => 10, 'unit_cost' => 20, 'discount' => 0],
            ],
            'payments' => [
                ['payment_method_id' => $this->cash->id, 'amount' => 999],
            ],
        ]);

        $response->assertSessionHasErrors('purchase');
        $this->resumeTenantContext();
        $this->assertSame(0, Purchase::count());
    }

    public function test_a_confirmed_purchase_can_be_cancelled_through_the_form(): void
    {
        $this->login();
        $this->post("{$this->baseUrl}/purchases", [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['product_id' => $this->product->id, 'unit_id' => $this->meter->id, 'quantity' => 10, 'unit_cost' => 20, 'discount' => 0],
            ],
            'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 200]],
        ]);
        $this->resumeTenantContext();
        $purchase = Purchase::first();

        $response = $this->post("{$this->baseUrl}/purchases/{$purchase->id}/cancel");

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('cancelled', $purchase->fresh()->status->value);
    }

    public function test_a_return_can_be_recorded_through_the_form(): void
    {
        $purchase = app(ConfirmPurchase::class)->handle(
            supplierId: $this->supplier->id,
            warehouseId: $this->warehouse->id,
            employeeId: null,
            createdBy: $this->managerUser->id,
            lines: [new PurchaseLine($this->product, $this->meter->id, '100.0000', '20.00')],
            payments: [],
        );
        $this->login();

        $response = $this->post("{$this->baseUrl}/purchases/returns", [
            'purchase_id' => $purchase->id,
            'purchase_item_id' => $purchase->items->first()->id,
            'quantity' => 20,
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('80.0000', $purchase->items->first()->fresh()->quantityEligibleForReturn());
    }
}
