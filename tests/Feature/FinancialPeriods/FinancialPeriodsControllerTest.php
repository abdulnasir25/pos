<?php

namespace Tests\Feature\FinancialPeriods;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FinancialPeriodsControllerTest extends TestCase
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
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'financial_periods.manage')->first());
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

    public function test_a_user_without_financial_periods_manage_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->get("{$this->baseUrl}/financial-periods");

        $response->assertForbidden();
    }

    public function test_the_page_lists_periods(): void
    {
        FinancialPeriod::create(['period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'status' => 'open']);
        $this->login();

        $response = $this->get("{$this->baseUrl}/financial-periods");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('FinancialPeriods/Index')
            ->has('periods', 1)
        );
    }

    public function test_a_period_can_be_created_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/financial-periods", [
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
        ]);

        $response->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('open', FinancialPeriod::first()->status->value);
    }

    public function test_an_overlapping_period_shows_an_error_not_a_crash(): void
    {
        FinancialPeriod::create(['period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'status' => 'open']);
        $this->login();

        $response = $this->post("{$this->baseUrl}/financial-periods", [
            'period_start' => '2026-01-15',
            'period_end' => '2026-02-15',
        ]);

        $response->assertSessionHasErrors('period');
    }

    public function test_a_period_can_move_through_the_full_lifecycle(): void
    {
        $period = FinancialPeriod::create(['period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'status' => 'open']);
        $this->login();

        $this->post("{$this->baseUrl}/financial-periods/{$period->id}/calculation")->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('calculating', $period->fresh()->status->value);

        $this->login();
        $this->post("{$this->baseUrl}/financial-periods/{$period->id}/review")->assertRedirect();
        $this->resumeTenantContext();
        $fresh = $period->fresh();
        $this->assertSame('under_review', $fresh->status->value);
        $this->assertSame($this->managerUser->id, $fresh->reviewed_by);

        $this->login();
        $this->post("{$this->baseUrl}/financial-periods/{$period->id}/close")->assertRedirect();
        $this->resumeTenantContext();
        $this->assertSame('closed', $period->fresh()->status->value);
        $this->assertNotNull($period->fresh()->closed_at);
    }

    public function test_skipping_a_transition_shows_an_error_not_a_crash(): void
    {
        $period = FinancialPeriod::create(['period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'status' => 'open']);
        $this->login();

        $response = $this->post("{$this->baseUrl}/financial-periods/{$period->id}/review");

        $response->assertSessionHasErrors('transition');
        $this->resumeTenantContext();
        $this->assertSame('open', $period->fresh()->status->value);
    }

    public function test_a_closed_period_cannot_be_transitioned_again(): void
    {
        $period = FinancialPeriod::create(['period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'status' => 'closed', 'closed_at' => now()]);
        $this->login();

        $response = $this->post("{$this->baseUrl}/financial-periods/{$period->id}/close");

        $response->assertSessionHasErrors('transition');
    }
}
