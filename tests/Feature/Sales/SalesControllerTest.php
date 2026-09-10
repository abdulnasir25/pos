<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Actions\RecordOpeningStock;
use App\Modules\Inventory\Support\StockLevelService;
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

class SalesControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private string $baseUrl;
    private Tenant $tenant;
    private Unit $meter;
    private Warehouse $warehouse;
    private Product $product;
    private Customer $customer;
    private User $managerUser;
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
        $this->customer = Customer::create(['name' => 'Ahmed', 'balance' => '0.00', 'status' => 'active']);
        $this->cash = PaymentMethod::create(['name' => 'Cash']);

        app(RecordOpeningStock::class)->handle($this->product, $this->warehouse->id, $this->meter->id, '100.0000', '20.0000');

        $this->managerUser = User::create(['name' => 'Manager', 'email' => 'manager@alfateh.test', 'password' => bcrypt('secret')]);
        $managerRole = Role::where('slug', 'manager')->first();
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'sales.create')->first());
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'sales.view')->first());
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'sales.cancel')->first());
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'sales.return')->first());
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

    private function confirmSale(int $quantity = 5, float $unitPrice = 50): Sale
    {
        $this->login();

        $this->post("{$this->baseUrl}/pos/sale", [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['product_id' => $this->product->id, 'unit_id' => $this->meter->id, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'discount' => 0],
            ],
            'payments' => [
                ['payment_method_id' => $this->cash->id, 'amount' => $quantity * $unitPrice],
            ],
        ]);

        $this->resumeTenantContext();

        return Sale::firstOrFail();
    }

    public function test_a_guest_is_redirected_away_from_sales_history(): void
    {
        $this->get("{$this->baseUrl}/sales")->assertRedirect('/login');
    }

    public function test_a_user_without_sales_view_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $this->actingAs($noPermUser)->get("{$this->baseUrl}/sales")->assertForbidden();
    }

    public function test_the_list_page_shows_a_confirmed_sale(): void
    {
        $sale = $this->confirmSale();
        $this->login();

        $response = $this->get("{$this->baseUrl}/sales");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Sales/Index')
            ->has('sales', 1)
            ->where('sales.0.id', $sale->id)
            ->where('sales.0.status', 'confirmed')
        );
    }

    public function test_the_detail_page_shows_line_items_and_eligible_return_quantity(): void
    {
        $sale = $this->confirmSale(quantity: 5);
        $this->login();

        $response = $this->get("{$this->baseUrl}/sales/{$sale->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Sales/Show')
            ->where('sale.status', 'confirmed')
            ->has('items', 1)
            ->where('items.0.product', 'Cotton')
            ->where('items.0.eligible_for_return', '5.0000')
        );
    }

    public function test_a_confirmed_sale_can_be_cancelled_through_the_form(): void
    {
        $sale = $this->confirmSale(quantity: 5, unitPrice: 50);
        $this->login();

        $response = $this->post("{$this->baseUrl}/sales/{$sale->id}/cancel");

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('cancelled', $sale->fresh()->status->value);
        $this->assertSame('100.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
    }

    public function test_cancelling_an_already_cancelled_sale_shows_an_error_not_a_crash(): void
    {
        $sale = $this->confirmSale();
        $this->login();
        $this->post("{$this->baseUrl}/sales/{$sale->id}/cancel");

        $this->login();
        $response = $this->post("{$this->baseUrl}/sales/{$sale->id}/cancel");

        $response->assertSessionHasErrors('sale');
    }

    public function test_a_user_without_sales_cancel_permission_cannot_cancel(): void
    {
        $sale = $this->confirmSale();
        $viewOnlyUser = User::create(['name' => 'ViewOnly', 'email' => 'viewonly@alfateh.test', 'password' => bcrypt('secret')]);
        $cashierRole = Role::where('slug', 'cashier')->first();
        app(GrantPermissionToRole::class)->handle($cashierRole, Permission::where('slug', 'sales.view')->first());
        app(AssignRoleToUser::class)->handle($viewOnlyUser, $cashierRole);

        $response = $this->actingAs($viewOnlyUser)->post("{$this->baseUrl}/sales/{$sale->id}/cancel");

        $response->assertForbidden();
    }

    public function test_a_partial_return_can_be_processed_through_the_form(): void
    {
        $sale = $this->confirmSale(quantity: 10, unitPrice: 50);
        $this->resumeTenantContext();
        $item = $sale->items()->first();

        $this->login();
        $response = $this->post("{$this->baseUrl}/sales/{$sale->id}/returns", [
            'lines' => [
                ['sale_item_id' => $item->id, 'quantity' => 4],
            ],
            'notes' => 'Wrong colour',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('confirmed', $sale->fresh()->status->value);
        $this->assertSame('94.0000', app(StockLevelService::class)->currentStock($this->product->id, $this->warehouse->id));
        // The sale was paid in full, so crediting the return leaves the
        // shop owing the customer — a negative balance.
        $this->assertSame('-200.00', $this->customer->fresh()->balance);
    }

    public function test_returning_every_item_marks_the_sale_refunded(): void
    {
        $sale = $this->confirmSale(quantity: 5, unitPrice: 50);
        $this->resumeTenantContext();
        $item = $sale->items()->first();

        $this->login();
        $this->post("{$this->baseUrl}/sales/{$sale->id}/returns", [
            'lines' => [['sale_item_id' => $item->id, 'quantity' => 5]],
        ]);

        $this->resumeTenantContext();
        $this->assertSame('refunded', $sale->fresh()->status->value);
    }

    public function test_returning_more_than_eligible_shows_an_error_not_a_crash(): void
    {
        $sale = $this->confirmSale(quantity: 5, unitPrice: 50);
        $this->resumeTenantContext();
        $item = $sale->items()->first();

        $this->login();
        $response = $this->post("{$this->baseUrl}/sales/{$sale->id}/returns", [
            'lines' => [['sale_item_id' => $item->id, 'quantity' => 10]],
        ]);

        $response->assertSessionHasErrors('return');
    }

    public function test_a_user_without_sales_return_permission_cannot_process_a_return(): void
    {
        $sale = $this->confirmSale();
        $this->resumeTenantContext();
        $item = $sale->items()->first();
        $viewOnlyUser = User::create(['name' => 'ViewOnly', 'email' => 'viewonly@alfateh.test', 'password' => bcrypt('secret')]);
        $cashierRole = Role::where('slug', 'cashier')->first();
        app(GrantPermissionToRole::class)->handle($cashierRole, Permission::where('slug', 'sales.view')->first());
        app(AssignRoleToUser::class)->handle($viewOnlyUser, $cashierRole);

        $response = $this->actingAs($viewOnlyUser)->post("{$this->baseUrl}/sales/{$sale->id}/returns", [
            'lines' => [['sale_item_id' => $item->id, 'quantity' => 1]],
        ]);

        $response->assertForbidden();
    }
}
