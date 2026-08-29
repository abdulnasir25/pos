<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AccountingControllerTest extends TestCase
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
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'accounting.view')->first());
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

    public function test_the_accounting_view_permission_exists_in_the_baseline_seed(): void
    {
        $this->assertNotNull(Permission::where('slug', 'accounting.view')->first());
    }

    public function test_a_user_without_accounting_view_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->get("{$this->baseUrl}/accounting");

        $response->assertForbidden();
    }

    public function test_the_page_lists_the_seeded_chart_of_accounts(): void
    {
        $this->login();

        $response = $this->get("{$this->baseUrl}/accounting");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/Index')
            ->has('accounts', 13)
        );
    }

    public function test_a_sub_account_can_be_added_through_the_form(): void
    {
        $parent = Account::where('code', '3000')->first();
        $this->login();

        $response = $this->post("{$this->baseUrl}/accounting/accounts", [
            'code' => '3001',
            'name' => 'Ahmed - Capital',
            'type' => 'equity',
            'parent_id' => $parent->id,
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame($parent->id, Account::where('code', '3001')->first()->parent_id);
    }

    public function test_a_duplicate_account_code_shows_an_error_not_a_crash(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/accounting/accounts", [
            'code' => '1000',
            'name' => 'Duplicate',
            'type' => 'asset',
        ]);

        $response->assertSessionHasErrors('account');
    }

    public function test_a_balanced_entry_can_be_posted_through_the_form(): void
    {
        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4000')->first();
        $this->login();

        $response = $this->post("{$this->baseUrl}/accounting/entries", [
            'entry_date' => '2026-01-15',
            'description' => 'Cash sale',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 5000, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 5000],
            ],
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_an_unbalanced_entry_shows_an_error_not_a_crash(): void
    {
        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4000')->first();
        $this->login();

        $response = $this->post("{$this->baseUrl}/accounting/entries", [
            'entry_date' => '2026-01-15',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 5000, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 4000],
            ],
        ]);

        $response->assertSessionHasErrors('entry');
        $this->resumeTenantContext();
        $this->assertSame(0, JournalEntry::count());
    }
}
