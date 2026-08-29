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

class AccessControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private string $baseUrl;
    private Tenant $tenant;

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
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    public function test_a_guest_is_redirected_away_from_the_access_page(): void
    {
        $response = $this->get("{$this->baseUrl}/access");

        $response->assertRedirect('/login');
    }

    public function test_an_authenticated_user_can_view_their_roles_and_permissions(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret123')]);
        $superAdmin = Role::where('slug', 'super_admin')->first();
        app(AssignRoleToUser::class)->handle($user, $superAdmin);

        $response = $this->actingAs($user)->get("{$this->baseUrl}/access");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Access/Index')
            ->where('auth.user.roles', ['super_admin'])
        );
    }
}
