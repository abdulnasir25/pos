<?php

namespace Tests\Feature\Warehouses;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WarehousesControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private string $baseUrl;
    private Tenant $tenant;
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

        $this->managerUser = User::create(['name' => 'Manager', 'email' => 'manager@alfateh.test', 'password' => bcrypt('secret')]);
        $managerRole = Role::where('slug', 'manager')->first();
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'warehouses.manage')->first());
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

    public function test_the_warehouses_manage_permission_exists_in_the_baseline_seed(): void
    {
        $this->assertNotNull(Permission::where('slug', 'warehouses.manage')->first());
    }

    public function test_a_user_without_warehouses_manage_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->get("{$this->baseUrl}/warehouses");

        $response->assertForbidden();
    }

    public function test_the_page_lists_warehouses(): void
    {
        Warehouse::create(['name' => 'Main Store', 'status' => 'active']);
        $this->login();

        $response = $this->get("{$this->baseUrl}/warehouses");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Warehouses/Index')
            ->has('warehouses', 1)
        );
    }

    public function test_a_warehouse_can_be_added_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/warehouses", ['name' => 'Main Store']);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, Warehouse::count());
        $this->assertSame('active', Warehouse::first()->status);
    }

    public function test_a_duplicate_warehouse_name_shows_an_error_not_a_crash(): void
    {
        Warehouse::create(['name' => 'Main Store', 'status' => 'active']);
        $this->login();

        $response = $this->post("{$this->baseUrl}/warehouses", ['name' => 'Main Store']);

        $response->assertSessionHasErrors('name');
    }

    public function test_a_warehouse_can_be_renamed_through_the_form(): void
    {
        $warehouse = Warehouse::create(['name' => 'Main Store', 'status' => 'active']);
        $this->login();

        $response = $this->post("{$this->baseUrl}/warehouses/{$warehouse->id}", ['name' => 'Main Branch']);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('Main Branch', $warehouse->fresh()->name);
    }

    public function test_a_warehouse_can_be_deactivated_and_reactivated(): void
    {
        $warehouse = Warehouse::create(['name' => 'Main Store', 'status' => 'active']);
        $this->login();

        $this->post("{$this->baseUrl}/warehouses/{$warehouse->id}/toggle-status")->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('inactive', $warehouse->fresh()->status);

        $this->login();
        $this->post("{$this->baseUrl}/warehouses/{$warehouse->id}/toggle-status")->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('active', $warehouse->fresh()->status);
    }
}
