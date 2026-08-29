<?php

namespace Tests\Feature\CashRegister;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\CashRegister\Actions\CreateFinancialAccount;
use App\Modules\CashRegister\Actions\OpenCashRegisterSession;
use App\Modules\CashRegister\Models\CashRegisterSession;
use App\Modules\CashRegister\Models\FinancialAccount;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CashRegisterControllerTest extends TestCase
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
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'cash_register.manage')->first());
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

    public function test_the_cash_register_manage_permission_exists_in_the_baseline_seed(): void
    {
        $this->assertNotNull(Permission::where('slug', 'cash_register.manage')->first());
    }

    public function test_a_user_without_cash_register_manage_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->get("{$this->baseUrl}/cash-register");

        $response->assertForbidden();
    }

    public function test_an_account_can_be_added_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/cash-register/accounts", [
            'name' => 'Main Till',
            'account_type' => 'cash',
            'opening_balance' => 5000,
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, FinancialAccount::where('name', 'Main Till')->count());
    }

    public function test_the_page_shows_the_open_session_for_an_account(): void
    {
        $account = app(CreateFinancialAccount::class)->handle('Main Till', 'cash');
        app(OpenCashRegisterSession::class)->handle($account, $this->managerUser->id, '3000.00');
        $this->login();

        $response = $this->get("{$this->baseUrl}/cash-register");

        $response->assertInertia(fn ($page) => $page
            ->component('CashRegister/Index')
            ->where('accounts.0.open_session.opening_float', '3000.00')
        );
    }

    public function test_a_session_can_be_opened_through_the_form(): void
    {
        $account = app(CreateFinancialAccount::class)->handle('Main Till', 'cash');
        $this->login();

        $response = $this->post("{$this->baseUrl}/cash-register/sessions", [
            'financial_account_id' => $account->id,
            'opening_float' => 3000,
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, CashRegisterSession::where('financial_account_id', $account->id)->count());
    }

    public function test_opening_a_second_session_shows_an_error_not_a_crash(): void
    {
        $account = app(CreateFinancialAccount::class)->handle('Main Till', 'cash');
        app(OpenCashRegisterSession::class)->handle($account, $this->managerUser->id, '3000.00');
        $this->login();

        $response = $this->post("{$this->baseUrl}/cash-register/sessions", [
            'financial_account_id' => $account->id,
            'opening_float' => 3000,
        ]);

        $response->assertSessionHasErrors('session');
    }

    public function test_a_session_can_be_closed_through_the_form(): void
    {
        $account = app(CreateFinancialAccount::class)->handle('Main Till', 'cash');
        $session = app(OpenCashRegisterSession::class)->handle($account, $this->managerUser->id, '3000.00');
        $this->login();

        $response = $this->post("{$this->baseUrl}/cash-register/sessions/{$session->id}/close", [
            'counted_closing' => 3450,
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('closed', $session->fresh()->status->value);
        $this->assertSame('3450.00', $session->fresh()->counted_closing);
    }

    public function test_closing_an_already_closed_session_shows_an_error_not_a_crash(): void
    {
        $account = app(CreateFinancialAccount::class)->handle('Main Till', 'cash');
        $session = app(OpenCashRegisterSession::class)->handle($account, $this->managerUser->id, '3000.00');
        $this->login();
        $this->post("{$this->baseUrl}/cash-register/sessions/{$session->id}/close", ['counted_closing' => 3000]);

        $response = $this->post("{$this->baseUrl}/cash-register/sessions/{$session->id}/close", ['counted_closing' => 3000]);

        $response->assertSessionHasErrors('session');
    }
}
