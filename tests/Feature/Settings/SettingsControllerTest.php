<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Settings\Models\Setting;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
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
        app(GrantPermissionToRole::class)->handle($managerRole, Permission::where('slug', 'settings.manage')->first());
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

    public function test_a_guest_is_redirected_away_from_settings(): void
    {
        $this->get("{$this->baseUrl}/settings")->assertRedirect('/login');
    }

    public function test_a_user_without_settings_manage_permission_is_forbidden(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $this->actingAs($noPermUser)->get("{$this->baseUrl}/settings")->assertForbidden();
    }

    public function test_visiting_settings_the_first_time_defaults_the_shop_name_to_the_tenants_name(): void
    {
        $this->login();

        $response = $this->get("{$this->baseUrl}/settings");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Index')
            ->where('settings.shop_name', 'Al-Fateh Cloth House')
            ->where('settings.currency_symbol', 'Rs.')
        );
        $this->resumeTenantContext();
        $this->assertSame(1, Setting::count());
    }

    public function test_settings_can_be_updated_through_the_form(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/settings", [
            'shop_name' => 'Al-Fateh Cloth House Ltd',
            'address' => 'Main Bazaar, Faisalabad',
            'phone' => '0300-1234567',
            'currency_symbol' => 'PKR',
            'receipt_footer' => 'Exchange within 7 days with receipt.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->resumeTenantContext();
        $setting = Setting::first();
        $this->assertSame('Al-Fateh Cloth House Ltd', $setting->shop_name);
        $this->assertSame('Main Bazaar, Faisalabad', $setting->address);
        $this->assertSame('PKR', $setting->currency_symbol);
        $this->assertSame('Exchange within 7 days with receipt.', $setting->receipt_footer);
    }

    public function test_updating_settings_a_second_time_edits_the_same_row(): void
    {
        $this->login();
        $this->post("{$this->baseUrl}/settings", ['shop_name' => 'First Name', 'currency_symbol' => 'Rs.']);

        $this->login();
        $this->post("{$this->baseUrl}/settings", ['shop_name' => 'Second Name', 'currency_symbol' => 'Rs.']);

        $this->resumeTenantContext();
        $this->assertSame(1, Setting::count());
        $this->assertSame('Second Name', Setting::first()->shop_name);
    }

    public function test_shop_name_is_required(): void
    {
        $this->login();

        $response = $this->post("{$this->baseUrl}/settings", ['shop_name' => '', 'currency_symbol' => 'Rs.']);

        $response->assertSessionHasErrors('shop_name');
    }

    public function test_a_user_without_permission_cannot_update_settings(): void
    {
        $noPermUser = User::create(['name' => 'NoPerm', 'email' => 'noperm@alfateh.test', 'password' => bcrypt('secret')]);

        $response = $this->actingAs($noPermUser)->post("{$this->baseUrl}/settings", [
            'shop_name' => 'Hacked Name',
            'currency_symbol' => 'Rs.',
        ]);

        $response->assertForbidden();
    }
}
