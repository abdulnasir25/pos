<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use App\Modules\Accounting\Actions\CreateAccount;
use App\Modules\Accounting\Actions\RecordJournalEntry;
use App\Modules\Accounting\DTOs\JournalLine;
use App\Modules\Accounting\Exceptions\DuplicateAccountCodeException;
use App\Modules\Accounting\Exceptions\InvalidJournalLineException;
use App\Modules\Accounting\Exceptions\UnbalancedJournalEntryException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Support\GetAccountBalance;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AccountingTest extends TestCase
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

    // --- Seeded chart --------------------------------------------------

    public function test_the_standard_chart_of_accounts_is_seeded_on_provisioning(): void
    {
        $this->assertSame(13, Account::count());
        $this->assertSame('Cash and Bank', Account::where('code', '1000')->first()->name);
        $this->assertSame('Sales Revenue', Account::where('code', '4000')->first()->name);
    }

    // --- CreateAccount --------------------------------------------------

    public function test_a_new_account_can_be_created(): void
    {
        $account = app(CreateAccount::class)->handle('1300', 'Prepaid Rent', 'asset');

        $this->assertSame('Prepaid Rent', $account->name);
    }

    public function test_a_sub_account_can_be_created_under_a_parent(): void
    {
        $capital = Account::where('code', '3000')->first();

        $sub = app(CreateAccount::class)->handle('3001', 'Ahmed - Capital', 'equity', $capital);

        $this->assertSame($capital->id, $sub->parent_id);
    }

    public function test_a_duplicate_account_code_is_refused(): void
    {
        $this->expectException(DuplicateAccountCodeException::class);

        app(CreateAccount::class)->handle('1000', 'Duplicate Cash', 'asset');
    }

    // --- RecordJournalEntry ------------------------------------------------

    public function test_a_balanced_entry_can_be_posted(): void
    {
        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4000')->first();

        $entry = app(RecordJournalEntry::class)->handle(
            entryDate: '2026-01-15',
            lines: [
                new JournalLine($cash->id, debit: '5000.00'),
                new JournalLine($revenue->id, credit: '5000.00'),
            ],
            createdBy: $this->admin->id,
            description: 'Cash sale',
        );

        $this->assertCount(2, $entry->lines);
        $this->assertSame('Cash sale', $entry->description);
    }

    public function test_an_unbalanced_entry_is_refused(): void
    {
        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4000')->first();

        $this->expectException(UnbalancedJournalEntryException::class);

        app(RecordJournalEntry::class)->handle(
            entryDate: '2026-01-15',
            lines: [
                new JournalLine($cash->id, debit: '5000.00'),
                new JournalLine($revenue->id, credit: '4999.00'),
            ],
            createdBy: $this->admin->id,
        );
    }

    public function test_a_line_with_both_debit_and_credit_is_refused(): void
    {
        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4000')->first();

        $this->expectException(InvalidJournalLineException::class);

        app(RecordJournalEntry::class)->handle(
            entryDate: '2026-01-15',
            lines: [
                new JournalLine($cash->id, debit: '100.00', credit: '100.00'),
                new JournalLine($revenue->id, credit: '100.00'),
            ],
            createdBy: $this->admin->id,
        );
    }

    public function test_a_line_with_neither_debit_nor_credit_is_refused(): void
    {
        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4000')->first();

        $this->expectException(InvalidJournalLineException::class);

        app(RecordJournalEntry::class)->handle(
            entryDate: '2026-01-15',
            lines: [
                new JournalLine($cash->id),
                new JournalLine($revenue->id, credit: '0.00'),
            ],
            createdBy: $this->admin->id,
        );
    }

    public function test_no_entry_is_persisted_when_validation_fails(): void
    {
        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4000')->first();

        try {
            app(RecordJournalEntry::class)->handle(
                entryDate: '2026-01-15',
                lines: [
                    new JournalLine($cash->id, debit: '100.00'),
                    new JournalLine($revenue->id, credit: '50.00'),
                ],
                createdBy: $this->admin->id,
            );
        } catch (UnbalancedJournalEntryException) {
            // expected
        }

        $this->assertSame(0, \App\Modules\Accounting\Models\JournalEntry::count());
    }

    // --- GetAccountBalance ---------------------------------------------------

    public function test_a_debit_normal_accounts_balance_increases_with_debits(): void
    {
        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4000')->first();

        app(RecordJournalEntry::class)->handle('2026-01-15', [
            new JournalLine($cash->id, debit: '5000.00'),
            new JournalLine($revenue->id, credit: '5000.00'),
        ], $this->admin->id);

        $this->assertSame('5000.00', app(GetAccountBalance::class)->handle($cash->fresh()));
    }

    public function test_a_credit_normal_accounts_balance_increases_with_credits(): void
    {
        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4000')->first();

        app(RecordJournalEntry::class)->handle('2026-01-15', [
            new JournalLine($cash->id, debit: '5000.00'),
            new JournalLine($revenue->id, credit: '5000.00'),
        ], $this->admin->id);

        $this->assertSame('5000.00', app(GetAccountBalance::class)->handle($revenue->fresh()));
    }

    public function test_multiple_entries_accumulate_into_the_balance(): void
    {
        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4000')->first();

        app(RecordJournalEntry::class)->handle('2026-01-15', [
            new JournalLine($cash->id, debit: '5000.00'),
            new JournalLine($revenue->id, credit: '5000.00'),
        ], $this->admin->id);

        app(RecordJournalEntry::class)->handle('2026-01-16', [
            new JournalLine($cash->id, debit: '2000.00'),
            new JournalLine($revenue->id, credit: '2000.00'),
        ], $this->admin->id);

        $this->assertSame('7000.00', app(GetAccountBalance::class)->handle($cash->fresh()));
    }
}
