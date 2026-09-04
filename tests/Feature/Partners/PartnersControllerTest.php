<?php

namespace Tests\Feature\Partners;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Partners\Actions\CreatePartner;
use App\Modules\Partners\Actions\IssuePartnerLoan;
use App\Modules\Partners\Actions\RecordOwnershipRebalance;
use App\Modules\Partners\Models\Partner;
use App\Modules\Partners\Models\PartnerCapitalEntry;
use App\Modules\Partners\Models\PartnerLoanRepayment;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PartnersControllerTest extends TestCase
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
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'partners.manage')->first());
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

    public function test_a_user_without_partners_manage_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->get("{$this->baseUrl}/partners");

        $response->assertForbidden();
    }

    public function test_the_partners_page_lists_partners_with_computed_balances(): void
    {
        $partner = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $this->login();

        $response = $this->get("{$this->baseUrl}/partners");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Partners/Index')
            ->has('partners', 1)
            ->where('partners.0.name', 'Ahmed')
            ->where('partners.0.capital_balance', '0.00')
        );
    }

    public function test_a_partner_can_be_added_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/partners", [
            'name' => 'Bilal',
            'phone' => '0300-1234567',
            'joined_at' => '2026-01-01',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, Partner::where('name', 'Bilal')->count());
    }

    public function test_a_partners_profile_can_be_updated_through_the_form(): void
    {
        $partner = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $this->login();

        $response = $this->post("{$this->baseUrl}/partners/{$partner->id}/profile", [
            'name' => 'Ahmed Khan',
            'phone' => '0300-9999999',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $fresh = $partner->fresh();
        $this->assertSame('Ahmed Khan', $fresh->name);
        $this->assertSame('0300-9999999', $fresh->phone);
    }

    public function test_a_partner_can_be_marked_as_exited_through_the_form(): void
    {
        $partner = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $this->login();

        $response = $this->post("{$this->baseUrl}/partners/{$partner->id}/exit", [
            'exited_at' => '2026-06-01',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $fresh = $partner->fresh();
        $this->assertSame('exited', $fresh->status->value);
        $this->assertSame('2026-06-01', $fresh->exited_at->toDateString());
    }

    public function test_ownership_can_be_rebalanced_through_the_form(): void
    {
        $a = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $b = app(CreatePartner::class)->handle('Bilal', '2026-01-01');
        $this->login();

        $response = $this->post("{$this->baseUrl}/partners/rebalance", [
            'effective_from' => '2026-01-01',
            'percentages' => [$a->id => 50, $b->id => 50],
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('50.00', $a->ownershipPeriods()->whereNull('effective_to')->first()->percentage);
    }

    public function test_a_rebalance_that_does_not_sum_to_100_shows_an_error_not_a_crash(): void
    {
        $a = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $b = app(CreatePartner::class)->handle('Bilal', '2026-01-01');
        $this->login();

        $response = $this->post("{$this->baseUrl}/partners/rebalance", [
            'effective_from' => '2026-01-01',
            'percentages' => [$a->id => 60, $b->id => 30],
        ]);

        $response->assertSessionHasErrors('rebalance');
    }

    public function test_a_capital_contribution_can_be_recorded_through_the_form(): void
    {
        $partner = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $this->login();

        $response = $this->post("{$this->baseUrl}/partners/capital", [
            'partner_id' => $partner->id,
            'type' => 'contribution',
            'amount' => 100000,
            'entry_date' => '2026-01-01',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, PartnerCapitalEntry::where('partner_id', $partner->id)->count());
    }

    public function test_a_loan_can_be_issued_and_repaid_through_the_forms(): void
    {
        $partner = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $this->login();

        $this->post("{$this->baseUrl}/partners/loans", [
            'partner_id' => $partner->id,
            'principal_amount' => 50000,
            'issued_at' => '2026-01-15',
        ]);

        $this->resumeTenantContext();
        $loan = $partner->loans()->first();
        $this->assertNotNull($loan);

        $response = $this->post("{$this->baseUrl}/partners/repayments", [
            'loan_id' => $loan->id,
            'amount' => 20000,
            'repaid_at' => '2026-02-01',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame(1, PartnerLoanRepayment::where('partner_loan_id', $loan->id)->count());
    }

    public function test_overpaying_a_loan_shows_an_error_not_a_crash(): void
    {
        $partner = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $loan = app(IssuePartnerLoan::class)->handle($partner, '50000.00', '2026-01-15', $this->managerUser->id);
        $this->login();

        $response = $this->post("{$this->baseUrl}/partners/repayments", [
            'loan_id' => $loan->id,
            'amount' => 60000,
            'repaid_at' => '2026-02-01',
        ]);

        $response->assertSessionHasErrors('repayment');
    }
}
