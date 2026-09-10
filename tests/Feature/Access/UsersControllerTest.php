<?php

namespace Tests\Feature\Access;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
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

class UsersControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private string $baseUrl;
    private Tenant $tenant;
    private User $managerUser;
    private Role $cashierRole;

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

        $this->cashierRole = Role::where('slug', 'cashier')->first();

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

    public function test_the_roles_manage_permission_exists_in_the_baseline_seed(): void
    {
        $this->assertNotNull(Permission::where('slug', 'roles.manage')->first());
    }

    public function test_a_user_without_roles_manage_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->get("{$this->baseUrl}/access/users");

        $response->assertForbidden();
    }

    public function test_a_staff_account_can_be_added_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/access/users", [
            'name' => 'Bilal',
            'email' => 'bilal@alfateh.test',
            'password' => 'password123',
            'role_id' => $this->cashierRole->id,
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $user = User::where('email', 'bilal@alfateh.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('active', $user->status);
        $this->assertTrue($user->hasRole('cashier'));
    }

    public function test_a_duplicate_email_shows_a_validation_error_not_a_crash(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/access/users", [
            'name' => 'Someone Else',
            'email' => 'manager@alfateh.test',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_a_staff_accounts_name_email_and_password_can_be_updated_through_the_form(): void
    {
        $this->login();
        $this->resumeTenantContext();
        $user = User::create(['name' => 'Bilal', 'email' => 'bilal@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->post("{$this->baseUrl}/access/users/{$user->id}", [
            'name' => 'Bilal Ahmed',
            'email' => 'bilal.ahmed@alfateh.test',
            'password' => 'newpassword123',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $fresh = $user->fresh();
        $this->assertSame('Bilal Ahmed', $fresh->name);
        $this->assertSame('bilal.ahmed@alfateh.test', $fresh->email);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpassword123', $fresh->password));
    }

    public function test_leaving_the_password_blank_keeps_the_current_one(): void
    {
        $this->login();
        $this->resumeTenantContext();
        $user = User::create(['name' => 'Bilal', 'email' => 'bilal@alfateh.test', 'password' => bcrypt('original-secret')]);

        $this->post("{$this->baseUrl}/access/users/{$user->id}", [
            'name' => 'Bilal',
            'email' => 'bilal@alfateh.test',
            'password' => '',
        ]);

        $this->resumeTenantContext();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('original-secret', $user->fresh()->password));
    }

    public function test_a_staff_accounts_status_can_be_toggled_through_the_form(): void
    {
        $this->login();
        $this->resumeTenantContext();
        $user = User::create(['name' => 'Bilal', 'email' => 'bilal@alfateh.test', 'password' => bcrypt('secret')]);

        $this->post("{$this->baseUrl}/access/users/{$user->id}/toggle-status")->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('inactive', $user->fresh()->status);

        $this->login();
        $this->post("{$this->baseUrl}/access/users/{$user->id}/toggle-status")->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_a_deactivated_account_cannot_log_in(): void
    {
        $this->resumeTenantContext();
        $user = User::create(['name' => 'Bilal', 'email' => 'bilal@alfateh.test', 'password' => bcrypt('secret'), 'status' => 'inactive']);

        $response = $this->post("{$this->baseUrl}/login", ['email' => 'bilal@alfateh.test', 'password' => 'secret']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deactivating_the_last_active_super_admin_is_refused(): void
    {
        $this->resumeTenantContext();
        $superAdminRole = Role::where('slug', 'super_admin')->first();
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@alfateh.test', 'password' => bcrypt('secret')]);
        app(AssignRoleToUser::class)->handle($owner, $superAdminRole);

        $this->login();
        $response = $this->post("{$this->baseUrl}/access/users/{$owner->id}/toggle-status");

        $response->assertSessionHasErrors('user');
        $this->resumeTenantContext();
        $this->assertSame('active', $owner->fresh()->status);
    }

    public function test_a_role_can_be_assigned_and_removed_through_the_form(): void
    {
        $this->login();
        $this->resumeTenantContext();
        $user = User::create(['name' => 'Bilal', 'email' => 'bilal@alfateh.test', 'password' => bcrypt('secret')]);

        $this->login();
        $assign = $this->post("{$this->baseUrl}/access/users/{$user->id}/roles", ['role_id' => $this->cashierRole->id]);
        $assign->assertRedirect();
        $this->resumeTenantContext();
        $this->assertTrue($user->fresh()->hasRole('cashier'));

        $this->login();
        $remove = $this->post("{$this->baseUrl}/access/users/{$user->id}/roles/{$this->cashierRole->id}/remove");
        $remove->assertRedirect();
        $this->resumeTenantContext();
        $this->assertFalse($user->fresh()->hasRole('cashier'));
    }

    public function test_removing_super_admin_from_the_last_holder_shows_an_error_not_a_crash(): void
    {
        $this->resumeTenantContext();
        $superAdminRole = Role::where('slug', 'super_admin')->first();
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@alfateh.test', 'password' => bcrypt('secret')]);
        app(AssignRoleToUser::class)->handle($owner, $superAdminRole);

        $this->login();
        $response = $this->post("{$this->baseUrl}/access/users/{$owner->id}/roles/{$superAdminRole->id}/remove");

        $response->assertSessionHasErrors('user');
        $this->resumeTenantContext();
        $this->assertTrue($owner->fresh()->hasRole('super_admin'));
    }
}
