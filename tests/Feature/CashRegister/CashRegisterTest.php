<?php

namespace Tests\Feature\CashRegister;

use App\Models\User;
use App\Modules\CashRegister\Actions\CloseCashRegisterSession;
use App\Modules\CashRegister\Actions\CreateFinancialAccount;
use App\Modules\CashRegister\Actions\OpenCashRegisterSession;
use App\Modules\CashRegister\Enums\CashRegisterSessionStatus;
use App\Modules\CashRegister\Exceptions\AccountNotACashAccountException;
use App\Modules\CashRegister\Exceptions\SessionAlreadyClosedException;
use App\Modules\CashRegister\Exceptions\SessionAlreadyOpenException;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CashRegisterTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private User $cashier;

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

        $this->cashier = User::create(['name' => 'Cashier', 'email' => 'cashier@alfateh.test', 'password' => bcrypt('secret')]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    public function test_a_financial_account_can_be_created(): void
    {
        $account = app(CreateFinancialAccount::class)->handle('Main Till', 'cash', '5000.00');

        $this->assertSame('Main Till', $account->name);
        $this->assertSame('5000.00', $account->opening_balance);
    }

    public function test_a_session_can_be_opened_on_a_cash_account(): void
    {
        $account = app(CreateFinancialAccount::class)->handle('Main Till', 'cash');

        $session = app(OpenCashRegisterSession::class)->handle($account, $this->cashier->id, '3000.00');

        $this->assertSame(CashRegisterSessionStatus::Open, $session->status);
        $this->assertSame('3000.00', $session->opening_float);
        $this->assertNotNull($session->opened_at);
    }

    public function test_a_session_cannot_be_opened_on_a_bank_account(): void
    {
        $account = app(CreateFinancialAccount::class)->handle('Business Bank', 'bank');

        $this->expectException(AccountNotACashAccountException::class);

        app(OpenCashRegisterSession::class)->handle($account, $this->cashier->id, '0.00');
    }

    public function test_a_second_session_cannot_be_opened_while_one_is_already_open(): void
    {
        $account = app(CreateFinancialAccount::class)->handle('Main Till', 'cash');
        app(OpenCashRegisterSession::class)->handle($account, $this->cashier->id, '3000.00');

        $this->expectException(SessionAlreadyOpenException::class);

        app(OpenCashRegisterSession::class)->handle($account, $this->cashier->id, '3000.00');
    }

    public function test_a_new_session_can_be_opened_after_the_previous_one_closes(): void
    {
        $account = app(CreateFinancialAccount::class)->handle('Main Till', 'cash');
        $first = app(OpenCashRegisterSession::class)->handle($account, $this->cashier->id, '3000.00');
        app(CloseCashRegisterSession::class)->handle($first, $this->cashier->id, '3500.00');

        $second = app(OpenCashRegisterSession::class)->handle($account, $this->cashier->id, '3500.00');

        $this->assertSame(CashRegisterSessionStatus::Open, $second->status);
        $this->assertSame(2, $account->sessions()->count());
    }

    public function test_closing_a_session_records_who_closed_it_and_the_counted_amount(): void
    {
        $account = app(CreateFinancialAccount::class)->handle('Main Till', 'cash');
        $session = app(OpenCashRegisterSession::class)->handle($account, $this->cashier->id, '3000.00');

        $manager = User::create(['name' => 'Manager', 'email' => 'manager@alfateh.test', 'password' => bcrypt('secret')]);
        $closed = app(CloseCashRegisterSession::class)->handle($session, $manager->id, '3450.00');

        $this->assertSame(CashRegisterSessionStatus::Closed, $closed->status);
        $this->assertSame($manager->id, $closed->closed_by);
        $this->assertSame('3450.00', $closed->counted_closing);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_closing_an_already_closed_session_is_refused(): void
    {
        $account = app(CreateFinancialAccount::class)->handle('Main Till', 'cash');
        $session = app(OpenCashRegisterSession::class)->handle($account, $this->cashier->id, '3000.00');
        app(CloseCashRegisterSession::class)->handle($session, $this->cashier->id, '3000.00');

        $this->expectException(SessionAlreadyClosedException::class);

        app(CloseCashRegisterSession::class)->handle($session->fresh(), $this->cashier->id, '3000.00');
    }
}
