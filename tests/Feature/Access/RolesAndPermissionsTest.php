<?php

namespace Tests\Feature\Access;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\CreatePermission;
use App\Modules\Access\Actions\CreateRole;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Actions\RemoveRoleFromUser;
use App\Modules\Access\Actions\RevokePermissionFromRole;
use App\Modules\Access\Exceptions\CannotRemoveLastSuperAdminException;
use App\Modules\Access\Exceptions\DuplicatePermissionSlugException;
use App\Modules\Access\Exceptions\DuplicateRoleSlugException;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = storage_path('framework/testing/tenants-'.uniqid());
        config(['tenancy.tenant_database_path' => $this->tenantDbPath]);

        $tenant = Tenant::create(['name' => 'Al-Fateh Cloth House', 'slug' => 'alfateh', 'database' => 'alfateh', 'status' => 'active']);

        File::ensureDirectoryExists($this->tenantDbPath);
        File::put($this->tenantDbPath.'/alfateh.sqlite', '');

        app(TenantConnectionFactory::class)->useConnectionFor($tenant);
        config(['database.default' => 'tenant']);

        Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--realpath' => false, '--force' => true]);

        app(TenantContext::class)->set($tenant);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    // --- Baseline seed (from the 0010 migration) ----------------------

    public function test_baseline_roles_are_seeded_on_tenant_provisioning(): void
    {
        $slugs = Role::pluck('slug')->all();

        $this->assertEqualsCanonicalizing(
            ['super_admin', 'partner', 'manager', 'cashier', 'employee'],
            $slugs,
        );
    }

    public function test_super_admin_role_is_protected_and_others_are_not(): void
    {
        $this->assertTrue(Role::where('slug', 'super_admin')->first()->is_protected);
        $this->assertFalse(Role::where('slug', 'partner')->first()->is_protected);
    }

    public function test_super_admin_is_seeded_with_every_baseline_permission(): void
    {
        $superAdmin = Role::where('slug', 'super_admin')->first();

        $this->assertSame(Permission::count(), $superAdmin->permissions()->count());
        $this->assertGreaterThan(0, Permission::count());
    }

    public function test_non_super_admin_roles_start_with_no_permissions(): void
    {
        $partner = Role::where('slug', 'partner')->first();

        $this->assertSame(0, $partner->permissions()->count());
    }

    // --- CreateRole / CreatePermission ---------------------------------

    public function test_a_role_can_be_created(): void
    {
        $role = app(CreateRole::class)->handle('Tailor', 'tailor');

        $this->assertSame('Tailor', $role->name);
        $this->assertFalse($role->is_protected);
    }

    public function test_creating_a_role_with_a_duplicate_slug_fails(): void
    {
        $this->expectException(DuplicateRoleSlugException::class);

        app(CreateRole::class)->handle('Another Partner', 'partner');
    }

    public function test_a_permission_can_be_created(): void
    {
        $permission = app(CreatePermission::class)->handle('purchases.manage', 'Manage supplier purchases');

        $this->assertSame('purchases.manage', $permission->slug);
    }

    public function test_creating_a_permission_with_a_duplicate_slug_fails(): void
    {
        $this->expectException(DuplicatePermissionSlugException::class);

        app(CreatePermission::class)->handle('sales.create', 'Duplicate');
    }

    // --- Granting/revoking permissions on a role -----------------------

    public function test_a_permission_can_be_granted_to_a_role(): void
    {
        $role = app(CreateRole::class)->handle('Tailor', 'tailor');
        $permission = Permission::where('slug', 'sales.view')->first();

        app(GrantPermissionToRole::class)->handle($role, $permission);

        $this->assertTrue($role->fresh()->permissions()->where('slug', 'sales.view')->exists());
    }

    public function test_granting_the_same_permission_twice_does_not_duplicate_the_pivot_row(): void
    {
        $role = app(CreateRole::class)->handle('Tailor', 'tailor');
        $permission = Permission::where('slug', 'sales.view')->first();

        app(GrantPermissionToRole::class)->handle($role, $permission);
        app(GrantPermissionToRole::class)->handle($role, $permission);

        $this->assertSame(1, $role->fresh()->permissions()->where('slug', 'sales.view')->count());
    }

    public function test_a_permission_can_be_revoked_from_a_role(): void
    {
        $role = app(CreateRole::class)->handle('Tailor', 'tailor');
        $permission = Permission::where('slug', 'sales.view')->first();

        app(GrantPermissionToRole::class)->handle($role, $permission);
        app(RevokePermissionFromRole::class)->handle($role, $permission);

        $this->assertFalse($role->fresh()->permissions()->where('slug', 'sales.view')->exists());
    }

    // --- Assigning/removing roles on a user -----------------------------

    public function test_a_role_can_be_assigned_to_a_user(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);
        $cashier = Role::where('slug', 'cashier')->first();

        app(AssignRoleToUser::class)->handle($user, $cashier);

        $this->assertTrue($user->hasRole('cashier'));
    }

    public function test_a_user_with_a_role_inherits_that_roles_permissions(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);
        $superAdmin = Role::where('slug', 'super_admin')->first();

        app(AssignRoleToUser::class)->handle($user, $superAdmin);

        $this->assertTrue($user->hasPermission('sales.create'));
        $this->assertTrue($user->hasPermission('roles.manage'));
    }

    public function test_a_user_without_the_role_does_not_have_its_permissions(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);

        $this->assertFalse($user->hasPermission('sales.create'));
        $this->assertFalse($user->hasRole('cashier'));
    }

    public function test_a_role_can_be_removed_from_a_user_who_is_not_the_last_super_admin(): void
    {
        $first = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);
        $second = User::create(['name' => 'Bilal', 'email' => 'bilal@alfateh.test', 'password' => bcrypt('secret')]);
        $superAdmin = Role::where('slug', 'super_admin')->first();

        app(AssignRoleToUser::class)->handle($first, $superAdmin);
        app(AssignRoleToUser::class)->handle($second, $superAdmin);

        app(RemoveRoleFromUser::class)->handle($first, $superAdmin);

        $this->assertFalse($first->hasRole('super_admin'));
        $this->assertTrue($second->hasRole('super_admin'));
    }

    public function test_a_non_protected_role_can_always_be_removed_even_if_it_is_the_only_one(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);
        $cashier = Role::where('slug', 'cashier')->first();

        app(AssignRoleToUser::class)->handle($user, $cashier);
        app(RemoveRoleFromUser::class)->handle($user, $cashier);

        $this->assertFalse($user->hasRole('cashier'));
    }

    public function test_removing_super_admin_from_the_last_holder_is_refused(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);
        $superAdmin = Role::where('slug', 'super_admin')->first();

        app(AssignRoleToUser::class)->handle($user, $superAdmin);

        $this->expectException(CannotRemoveLastSuperAdminException::class);

        app(RemoveRoleFromUser::class)->handle($user, $superAdmin);
    }

    public function test_removing_super_admin_from_the_last_holder_leaves_the_role_intact(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);
        $superAdmin = Role::where('slug', 'super_admin')->first();

        app(AssignRoleToUser::class)->handle($user, $superAdmin);

        try {
            app(RemoveRoleFromUser::class)->handle($user, $superAdmin);
        } catch (CannotRemoveLastSuperAdminException) {
            // expected
        }

        $this->assertTrue($user->fresh()->hasRole('super_admin'));
    }

    public function test_a_user_can_hold_more_than_one_role(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);
        $partner = Role::where('slug', 'partner')->first();
        $cashier = Role::where('slug', 'cashier')->first();

        app(AssignRoleToUser::class)->handle($user, $partner);
        app(AssignRoleToUser::class)->handle($user, $cashier);

        $this->assertTrue($user->hasRole('partner'));
        $this->assertTrue($user->hasRole('cashier'));
    }
}
