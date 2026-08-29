<?php

namespace Tests\Feature\AuditLog;

use App\Models\User;
use App\Modules\AuditLog\Actions\RecordAuditLog;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\AuditLog\Support\GetAuditTrailFor;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\RemoveRoleFromUser;
use App\Modules\Access\Models\Role;
use App\Modules\Partners\Actions\CreatePartner;
use App\Modules\Partners\Actions\RecordOwnershipRebalance;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private User $admin;

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

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@alfateh.test', 'password' => bcrypt('secret')]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    // --- Core -----------------------------------------------------------

    public function test_an_entry_can_be_recorded(): void
    {
        $entry = app(RecordAuditLog::class)->handle(
            userId: $this->admin->id,
            action: 'test.action',
            newValues: ['foo' => 'bar'],
        );

        $this->assertSame('test.action', $entry->action);
        $this->assertSame(['foo' => 'bar'], $entry->new_values);
        $this->assertNotNull($entry->created_at);
    }

    public function test_an_entry_can_be_recorded_with_no_user_for_system_actions(): void
    {
        $entry = app(RecordAuditLog::class)->handle(userId: null, action: 'system.migrated');

        $this->assertNull($entry->user_id);
    }

    public function test_the_audit_trail_for_an_entity_returns_entries_newest_first(): void
    {
        app(RecordAuditLog::class)->handle($this->admin->id, 'user.updated', User::class, $this->admin->id, ['name' => 'Old'], ['name' => 'New']);
        app(RecordAuditLog::class)->handle($this->admin->id, 'user.updated', User::class, $this->admin->id, ['name' => 'New'], ['name' => 'Newer']);

        $trail = app(GetAuditTrailFor::class)->handle(User::class, $this->admin->id);

        $this->assertCount(2, $trail);
        $this->assertSame('Newer', $trail->first()->new_values['name']);
    }

    // --- Wired: role assignment ------------------------------------------

    public function test_assigning_a_role_writes_an_audit_entry(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);
        $cashier = Role::where('slug', 'cashier')->first();

        app(AssignRoleToUser::class)->handle($user, $cashier, $this->admin->id);

        $trail = app(GetAuditTrailFor::class)->handle(User::class, $user->id);
        $this->assertCount(1, $trail);
        $this->assertSame('role.assigned', $trail->first()->action);
        $this->assertSame($this->admin->id, $trail->first()->user_id);
        $this->assertSame('cashier', $trail->first()->new_values['role']);
    }

    public function test_removing_a_role_writes_an_audit_entry(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);
        $cashier = Role::where('slug', 'cashier')->first();
        app(AssignRoleToUser::class)->handle($user, $cashier);

        app(RemoveRoleFromUser::class)->handle($user, $cashier, $this->admin->id);

        $entry = app(GetAuditTrailFor::class)->handle(User::class, $user->id)
            ->firstWhere('action', 'role.removed');
        $this->assertNotNull($entry);
        $this->assertSame('cashier', $entry->old_values['role']);
    }

    public function test_assigning_a_role_without_an_actor_still_logs_with_a_null_user(): void
    {
        $user = User::create(['name' => 'Ahmed', 'email' => 'ahmed@alfateh.test', 'password' => bcrypt('secret')]);
        $cashier = Role::where('slug', 'cashier')->first();

        app(AssignRoleToUser::class)->handle($user, $cashier);

        $entry = app(GetAuditTrailFor::class)->handle(User::class, $user->id)->first();
        $this->assertNull($entry->user_id);
    }

    // --- Wired: partner ownership rebalance ------------------------------

    public function test_an_ownership_rebalance_writes_an_audit_entry_with_old_and_new_percentages(): void
    {
        $a = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $b = app(CreatePartner::class)->handle('Bilal', '2026-01-01');
        app(RecordOwnershipRebalance::class)->handle([$a->id => '50.00', $b->id => '50.00'], '2026-01-01', $this->admin->id);

        app(RecordOwnershipRebalance::class)->handle([$a->id => '40.00', $b->id => '60.00'], '2026-03-01', $this->admin->id);

        $entry = AuditLog::where('action', 'partner_ownership.rebalanced')->latest('id')->first();
        $this->assertSame($this->admin->id, $entry->user_id);
        $this->assertSame('50.00', $entry->old_values['percentages'][$a->id]);
        $this->assertSame('40.00', $entry->new_values['percentages'][$a->id]);
    }
}
