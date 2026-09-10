<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Platform\Actions\ProvisionTenant;
use App\Modules\Platform\Exceptions\DuplicateTenantSlugException;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

/**
 * Exercises the real provisioning path — a real SQLite file gets
 * created and migrated (the tenant DB driver defaults to SQLite in
 * testing, same as every other tenant-provisioning test in this
 * suite) — so this asserts what tenants:create and the landlord "add a
 * shop" form both actually do, not a mocked version of it.
 */
class ProvisionTenantTest extends TestCase
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
        app(TenantContext::class)->clear();

        parent::tearDown();
    }

    public function test_a_tenant_is_provisioned_with_an_active_status_and_a_migrated_database(): void
    {
        $provisioned = app(ProvisionTenant::class)->handle('Al-Fateh Cloth House', 'alfateh', 'Owner', 'owner@alfateh.test');

        $this->assertSame('alfateh', $provisioned->tenant->slug);
        $this->assertSame('active', $provisioned->tenant->status);
        $this->assertFileExists($this->tenantDbPath.'/alfateh.sqlite');

        $pdo = new PDO('sqlite:'.$this->tenantDbPath.'/alfateh.sqlite');
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('products', $tables);
    }

    public function test_the_slug_is_derived_from_the_name_when_omitted(): void
    {
        $provisioned = app(ProvisionTenant::class)->handle('Karachi Fabrics', null, 'Owner', 'owner@karachi.test');

        $this->assertSame('karachi-fabrics', $provisioned->tenant->slug);
    }

    public function test_a_duplicate_slug_is_rejected_and_nothing_is_created(): void
    {
        app(ProvisionTenant::class)->handle('Al-Fateh Cloth House', 'alfateh', 'Owner', 'owner@alfateh.test');

        $this->expectException(DuplicateTenantSlugException::class);

        try {
            app(ProvisionTenant::class)->handle('Al-Fateh Again', 'alfateh', 'Owner', 'owner2@alfateh.test');
        } finally {
            $this->assertSame(1, Tenant::where('slug', 'alfateh')->count());
        }
    }

    public function test_the_owner_is_created_as_a_super_admin_who_can_log_in(): void
    {
        $provisioned = app(ProvisionTenant::class)->handle('Al-Fateh Cloth House', 'alfateh', 'Owner', 'owner@alfateh.test');

        $this->assertSame('owner@alfateh.test', $provisioned->ownerEmail);
        $this->assertNotEmpty($provisioned->ownerPassword);

        // Provisioning must leave the landlord connection as the
        // request default — it must not still be pointed at the
        // tenant it just finished setting up.
        $this->assertSame('landlord', config('database.default'));

        $login = $this->post("http://alfateh.pos.test/login", [
            'email' => 'owner@alfateh.test',
            'password' => $provisioned->ownerPassword,
        ]);

        $login->assertRedirect('/dashboard');

        app(TenantContext::class)->set($provisioned->tenant);
        config(['database.default' => app(TenantConnectionFactory::class)->useConnectionFor($provisioned->tenant)]);
        $owner = User::where('email', 'owner@alfateh.test')->firstOrFail();
        $this->assertTrue($owner->roles->contains('slug', 'super_admin'));
    }
}
