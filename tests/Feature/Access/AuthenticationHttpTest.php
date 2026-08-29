<?php

namespace Tests\Feature\Access;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Models\Role;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Exercises the actual HTTP routes (login/logout/dashboard) under the
 * 'web' + 'tenant' + HandleInertiaRequests pipeline, as opposed to
 * RolesAndPermissionsTest which calls Actions directly. Confirms the
 * whole chain — session, tenant DB resolution, auth guard, Inertia
 * shared props — works together, not just each piece in isolation.
 */
class AuthenticationHttpTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private string $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = storage_path('framework/testing/tenants-'.uniqid());
        config(['tenancy.tenant_database_path' => $this->tenantDbPath]);

        $tenant = Tenant::create(['name' => 'Al-Fateh Cloth House', 'slug' => 'alfateh', 'database' => 'alfateh', 'status' => 'active']);
        $this->baseUrl = 'http://alfateh.pos.test';

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

    public function test_a_guest_can_view_the_login_page(): void
    {
        $response = $this->get("{$this->baseUrl}/login");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    public function test_a_user_can_log_in_with_valid_credentials_and_is_redirected_to_the_dashboard(): void
    {
        User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret123')]);

        $response = $this->post("{$this->baseUrl}/login", [
            'email' => 'ahmed@alfateh.test',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_after_a_real_session_login_the_dashboard_loads_and_resolves_the_user_from_the_tenant_db(): void
    {
        // Deliberately not actingAs() here — that sets the guard's user
        // directly and skips SessionGuard's real retrieveById() lookup,
        // which is exactly the path that broke when 'auth' ran before
        // 'tenant' in the real middleware pipeline (caught via manual
        // browser verification, not by the actingAs()-based tests below).
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret123')]);
        $superAdmin = Role::where('slug', 'super_admin')->first();
        app(AssignRoleToUser::class)->handle($user, $superAdmin);

        $this->post("{$this->baseUrl}/login", [
            'email' => 'ahmed@alfateh.test',
            'password' => 'secret123',
        ])->assertRedirect('/dashboard');

        $response = $this->get("{$this->baseUrl}/dashboard");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('auth.user.email', 'ahmed@alfateh.test')
            ->where('auth.user.roles', ['super_admin'])
        );
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret123')]);

        $response = $this->post("{$this->baseUrl}/login", [
            'email' => 'ahmed@alfateh.test',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_guest_is_redirected_away_from_the_dashboard(): void
    {
        $response = $this->get("{$this->baseUrl}/dashboard");

        $response->assertRedirect('/login');
    }

    public function test_an_authenticated_user_can_view_the_dashboard_with_roles_and_permissions_shared(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret123')]);
        $superAdmin = Role::where('slug', 'super_admin')->first();
        app(AssignRoleToUser::class)->handle($user, $superAdmin);

        $response = $this->actingAs($user)->get("{$this->baseUrl}/dashboard");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('auth.user.email', 'ahmed@alfateh.test')
            ->where('auth.user.roles', ['super_admin'])
            ->has('auth.user.permissions', 15)
        );
    }

    public function test_an_authenticated_user_is_redirected_away_from_the_login_page(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret123')]);

        $response = $this->actingAs($user)->get("{$this->baseUrl}/login");

        $response->assertRedirect('/dashboard');
    }

    public function test_a_user_can_log_out(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret123')]);

        $response = $this->actingAs($user)->post("{$this->baseUrl}/logout");

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
