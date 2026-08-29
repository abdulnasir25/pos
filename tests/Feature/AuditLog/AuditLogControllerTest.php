<?php

namespace Tests\Feature\AuditLog;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\AuditLog\Actions\RecordAuditLog;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
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
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'audit_logs.view')->first());
        app(AssignRoleToUser::class)->handle($this->managerUser, $managerRole);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    private function login(): void
    {
        $this->post("{$this->baseUrl}/login", ['email' => 'manager@alfateh.test', 'password' => 'secret']);
    }

    public function test_the_audit_logs_view_permission_exists_in_the_baseline_seed(): void
    {
        $this->assertNotNull(Permission::where('slug', 'audit_logs.view')->first());
    }

    public function test_a_user_without_audit_logs_view_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->get("{$this->baseUrl}/audit-log");

        $response->assertForbidden();
    }

    public function test_entries_are_listed_newest_first_with_resolved_user_names(): void
    {
        app(RecordAuditLog::class)->handle($this->managerUser->id, 'test.first', newValues: ['x' => 1]);
        app(RecordAuditLog::class)->handle($this->managerUser->id, 'test.second', newValues: ['x' => 2]);
        $this->login();

        $response = $this->get("{$this->baseUrl}/audit-log");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('AuditLog/Index')
            ->where('entries.0.action', 'test.second')
            ->where('entries.0.user', 'Manager')
            ->where('entries.1.action', 'test.first')
        );
    }

    public function test_a_system_entry_with_no_user_shows_as_system(): void
    {
        app(RecordAuditLog::class)->handle(null, 'system.migrated');
        $this->login();

        $response = $this->get("{$this->baseUrl}/audit-log");

        $response->assertInertia(fn ($page) => $page->where('entries.0.user', 'System'));
    }

    public function test_entries_can_be_filtered_by_action(): void
    {
        // Not 'role.assigned'/'role.removed': setUp() already grants
        // the manager role, which itself writes a real 'role.assigned'
        // entry — using those names here would collide with it.
        app(RecordAuditLog::class)->handle($this->managerUser->id, 'test.alpha');
        app(RecordAuditLog::class)->handle($this->managerUser->id, 'test.beta');
        $this->login();

        $response = $this->get("{$this->baseUrl}/audit-log?action=test.alpha");

        $response->assertInertia(fn ($page) => $page->has('entries', 1)->where('entries.0.action', 'test.alpha'));
    }

    public function test_an_entry_with_an_auditable_entity_shows_its_type_and_id(): void
    {
        app(RecordAuditLog::class)->handle($this->managerUser->id, 'role.assigned', \App\Models\User::class, $this->managerUser->id, null, ['role' => 'cashier']);
        $this->login();

        $response = $this->get("{$this->baseUrl}/audit-log");

        $response->assertInertia(fn ($page) => $page->where('entries.0.auditable', "User #{$this->managerUser->id}"));
    }
}
