<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Exceptions\NoTenantResolvedException;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = storage_path('framework/testing/tenants-'.uniqid());
        config(['tenancy.tenant_database_path' => $this->tenantDbPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    private function createTenant(string $name, string $slug): Tenant
    {
        Artisan::call('tenants:create', ['name' => $name, '--slug' => $slug]);

        return Tenant::where('slug', $slug)->firstOrFail();
    }

    public function test_a_request_for_an_unknown_subdomain_is_rejected(): void
    {
        $this->get('http://nope.pos.test/_tenant/whoami')
            ->assertNotFound();
    }

    public function test_a_request_for_the_bare_central_domain_finds_no_tenant(): void
    {
        $this->get('http://pos.test/_tenant/whoami')
            ->assertNotFound();
    }

    public function test_the_bare_central_domain_root_shows_the_welcome_page(): void
    {
        $this->get('http://pos.test/')
            ->assertOk()
            ->assertSee('Platform Admin Login');
    }

    public function test_a_tenant_subdomain_root_redirects_to_that_tenants_login(): void
    {
        $this->createTenant('Al-Fateh Cloth House', 'alfateh');

        $this->get('http://alfateh.pos.test/')
            ->assertRedirect('/login');
    }

    public function test_a_resolved_tenant_gets_its_own_connection(): void
    {
        $this->createTenant('Al-Fateh Cloth House', 'alfateh');

        $this->get('http://alfateh.pos.test/_tenant/whoami')
            ->assertOk()
            ->assertJson([
                'tenant' => 'alfateh',
                'status' => 'active',
                'connection' => 'tenant',
            ]);
    }

    public function test_a_suspended_tenant_is_refused_before_any_tenant_code_runs(): void
    {
        $tenant = $this->createTenant('Zainab Fabrics', 'zainab');
        $tenant->update(['status' => 'suspended']);

        $this->get('http://zainab.pos.test/_tenant/whoami')
            ->assertForbidden();
    }

    public function test_data_written_for_one_tenant_is_invisible_to_another(): void
    {
        $tenantA = $this->createTenant('Al-Fateh Cloth House', 'alfateh');
        $tenantB = $this->createTenant('Zainab Fabrics', 'zainab');

        $connections = app(TenantConnectionFactory::class);

        $connections->useConnectionFor($tenantA);
        DB::connection('tenant')->table('users')->insert([
            'name' => 'Owner A',
            'email' => 'owner@alfateh.test',
            'password' => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $connections->useConnectionFor($tenantB);
        $this->assertSame(0, DB::connection('tenant')->table('users')->count());

        $connections->useConnectionFor($tenantA);
        $this->assertSame(1, DB::connection('tenant')->table('users')->count());
    }

    public function test_worker_reuse_never_leaks_a_tenant_connection_into_the_next_request(): void
    {
        $this->createTenant('Al-Fateh Cloth House', 'alfateh');

        $this->get('http://alfateh.pos.test/_tenant/whoami')->assertOk();

        // Simulates a reused PHP-FPM/Octane worker's next request: the
        // landlord Tenant model must resolve on the landlord connection
        // again, not still be pointed at alfateh's tenant connection.
        $this->assertSame('landlord', config('database.default'));
    }

    public function test_a_tenant_scoped_model_refuses_to_query_with_no_tenant_resolved(): void
    {
        $this->expectException(NoTenantResolvedException::class);

        User::query()->first();
    }

    public function test_gate_denies_authorization_across_a_landlord_and_tenant_connection_mismatch(): void
    {
        // What DenyCrossTenantAuthorization actually guarantees: the acting
        // user and the model being authorized must be on the *same named*
        // connection. That's a real boundary between "landlord" and
        // "tenant" — e.g. it stops a platform-admin session (landlord
        // connection) from ever being used to authorize an action against
        // a tenant-connection record, or vice versa.
        $tenant = $this->createTenant('Al-Fateh Cloth House', 'alfateh');
        app(TenantConnectionFactory::class)->useConnectionFor($tenant);
        config(['database.default' => 'tenant']);

        $tenantUser = User::create([
            'name' => 'Owner A',
            'email' => 'owner@alfateh.test',
            'password' => bcrypt('secret'),
        ]);

        $landlordRecord = $tenant; // an Eloquent model on the 'landlord' connection

        Gate::define('view-record', fn () => true);

        $this->assertFalse(Gate::forUser($tenantUser)->allows('view-record', $landlordRecord));
    }
}
