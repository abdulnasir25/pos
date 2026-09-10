<?php

namespace Tests\Feature\Access;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\CreateRole;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RolesControllerTest extends TestCase
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
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'roles.manage')->first());
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

    public function test_a_user_without_roles_manage_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->get("{$this->baseUrl}/access/roles");

        $response->assertForbidden();
    }

    public function test_the_page_lists_baseline_roles_with_their_permission_ids(): void
    {
        $this->login();

        $response = $this->get("{$this->baseUrl}/access/roles");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Access/Roles/Index')
            ->has('roles', 5)
        );
    }

    public function test_a_role_can_be_added_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/access/roles", ['name' => 'Tailor']);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertNotNull(Role::where('slug', 'tailor')->first());
    }

    public function test_a_duplicate_role_name_shows_an_error_not_a_crash(): void
    {
        $this->login();
        $this->resumeTenantContext();
        app(CreateRole::class)->handle('Tailor', 'tailor');

        $this->login();
        $response = $this->post("{$this->baseUrl}/access/roles", ['name' => 'Tailor']);

        $response->assertSessionHasErrors('role');
    }

    public function test_a_permission_can_be_granted_and_revoked_through_the_form(): void
    {
        $this->resumeTenantContext();
        $role = app(CreateRole::class)->handle('Tailor', 'tailor');
        $permission = Permission::where('slug', 'sales.view')->first();

        $this->login();
        $grant = $this->post("{$this->baseUrl}/access/roles/{$role->id}/permissions/{$permission->id}/toggle");
        $grant->assertRedirect();
        $this->resumeTenantContext();
        $this->assertTrue($role->fresh()->permissions()->where('slug', 'sales.view')->exists());

        $this->login();
        $revoke = $this->post("{$this->baseUrl}/access/roles/{$role->id}/permissions/{$permission->id}/toggle");
        $revoke->assertRedirect();
        $this->resumeTenantContext();
        $this->assertFalse($role->fresh()->permissions()->where('slug', 'sales.view')->exists());
    }

    public function test_the_super_admin_roles_permissions_cannot_be_changed(): void
    {
        $this->resumeTenantContext();
        $superAdminRole = Role::where('slug', 'super_admin')->first();
        $permission = Permission::where('slug', 'sales.view')->first();
        $countBefore = $superAdminRole->permissions()->count();

        $this->login();
        $response = $this->post("{$this->baseUrl}/access/roles/{$superAdminRole->id}/permissions/{$permission->id}/toggle");

        $response->assertSessionHasErrors('role');
        $this->resumeTenantContext();
        $this->assertSame($countBefore, $superAdminRole->fresh()->permissions()->count());
    }
}
