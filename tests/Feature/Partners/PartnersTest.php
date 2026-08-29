<?php

namespace Tests\Feature\Partners;

use App\Models\User;
use App\Modules\FinancialPeriods\Actions\CreateFinancialPeriod;
use App\Modules\Partners\Actions\AllocateProfitToPartners;
use App\Modules\Partners\Actions\CreatePartner;
use App\Modules\Partners\Actions\ExitPartner;
use App\Modules\Partners\Actions\IssuePartnerLoan;
use App\Modules\Partners\Actions\RecordCapitalContribution;
use App\Modules\Partners\Actions\RecordCapitalWithdrawal;
use App\Modules\Partners\Actions\RecordLoanRepayment;
use App\Modules\Partners\Actions\RecordOwnershipRebalance;
use App\Modules\Partners\Actions\RecordPartnerDistribution;
use App\Modules\Partners\Actions\UpdatePartnerProfile;
use App\Modules\Partners\Enums\LoanStatus;
use App\Modules\Partners\Enums\PartnerStatus;
use App\Modules\Partners\Exceptions\InvalidOwnershipDateRangeException;
use App\Modules\Partners\Exceptions\NoOwnershipDataForDateException;
use App\Modules\Partners\Exceptions\OwnershipPercentagesMustSumTo100Exception;
use App\Modules\Partners\Exceptions\RebalanceMustCoverEveryActivePartnerException;
use App\Modules\Partners\Exceptions\RepaymentExceedsOutstandingBalanceException;
use App\Modules\Partners\Models\Partner;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PartnersTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private User $admin;
    private PaymentMethod $bank;

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
        $this->bank = PaymentMethod::create(['name' => 'Bank Transfer']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    // --- Partner lifecycle ------------------------------------------------

    public function test_a_partner_can_be_created(): void
    {
        $partner = app(CreatePartner::class)->handle('Bilal', '2026-01-01', '0300-1234567');

        $this->assertSame('Bilal', $partner->name);
        $this->assertSame(PartnerStatus::Active, $partner->status);
        $this->assertNull($partner->exited_at);
    }

    public function test_a_partners_profile_can_be_updated(): void
    {
        $partner = app(CreatePartner::class)->handle('Bilal', '2026-01-01');

        app(UpdatePartnerProfile::class)->handle($partner, 'Bilal Ahmed', '0300-9999999');

        $this->assertSame('Bilal Ahmed', $partner->fresh()->name);
        $this->assertSame('0300-9999999', $partner->fresh()->phone);
    }

    public function test_a_partner_can_exit(): void
    {
        $partner = app(CreatePartner::class)->handle('Bilal', '2026-01-01');

        app(ExitPartner::class)->handle($partner, '2026-06-30');

        $partner->refresh();
        $this->assertSame(PartnerStatus::Exited, $partner->status);
        $this->assertSame('2026-06-30', $partner->exited_at->toDateString());
    }

    // --- Ownership rebalance ------------------------------------------------

    public function test_initial_ownership_can_be_set_for_two_equal_partners(): void
    {
        $a = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $b = app(CreatePartner::class)->handle('Bilal', '2026-01-01');

        app(RecordOwnershipRebalance::class)->handle([
            $a->id => '50.00',
            $b->id => '50.00',
        ], '2026-01-01');

        $this->assertSame('50.00', $a->ownershipPeriods()->whereNull('effective_to')->first()->percentage);
        $this->assertSame('50.00', $b->ownershipPeriods()->whereNull('effective_to')->first()->percentage);
    }

    public function test_rebalance_rejects_percentages_that_do_not_sum_to_100(): void
    {
        $a = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $b = app(CreatePartner::class)->handle('Bilal', '2026-01-01');

        $this->expectException(OwnershipPercentagesMustSumTo100Exception::class);

        app(RecordOwnershipRebalance::class)->handle([
            $a->id => '60.00',
            $b->id => '30.00',
        ], '2026-01-01');
    }

    public function test_rebalance_rejects_missing_an_active_partner(): void
    {
        $a = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        app(CreatePartner::class)->handle('Bilal', '2026-01-01');

        $this->expectException(RebalanceMustCoverEveryActivePartnerException::class);

        app(RecordOwnershipRebalance::class)->handle([
            $a->id => '100.00',
        ], '2026-01-01');
    }

    public function test_a_later_rebalance_closes_the_previous_open_period_and_opens_a_new_one(): void
    {
        $a = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $b = app(CreatePartner::class)->handle('Bilal', '2026-01-01');

        app(RecordOwnershipRebalance::class)->handle([$a->id => '50.00', $b->id => '50.00'], '2026-01-01');
        app(RecordOwnershipRebalance::class)->handle([$a->id => '40.00', $b->id => '60.00'], '2026-03-01');

        $aHistory = $a->ownershipPeriods()->orderBy('effective_from')->get();
        $this->assertCount(2, $aHistory);
        $this->assertSame('50.00', $aHistory[0]->percentage);
        $this->assertSame('2026-02-28', $aHistory[0]->effective_to->toDateString());
        $this->assertSame('40.00', $aHistory[1]->percentage);
        $this->assertNull($aHistory[1]->effective_to);
    }

    public function test_rebalance_rejects_a_new_effective_date_before_the_existing_open_period(): void
    {
        $a = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $b = app(CreatePartner::class)->handle('Bilal', '2026-01-01');

        app(RecordOwnershipRebalance::class)->handle([$a->id => '50.00', $b->id => '50.00'], '2026-03-01');

        $this->expectException(InvalidOwnershipDateRangeException::class);

        app(RecordOwnershipRebalance::class)->handle([$a->id => '40.00', $b->id => '60.00'], '2026-01-01');
    }

    // --- Capital ------------------------------------------------------------

    public function test_a_capital_contribution_creates_an_entry_and_a_positive_ledger_line(): void
    {
        $partner = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');

        app(RecordCapitalContribution::class)->handle($partner, '100000.00', '2026-01-01', $this->admin->id);

        $this->assertSame('100000.00', $partner->capitalEntries()->first()->amount);
        $this->assertSame('100000.00', $partner->ledgerEntries()->first()->amount);
    }

    public function test_a_capital_withdrawal_creates_a_negative_ledger_line(): void
    {
        $partner = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');

        app(RecordCapitalWithdrawal::class)->handle($partner, '20000.00', '2026-02-01', $this->admin->id);

        $this->assertSame('-20000.00', $partner->ledgerEntries()->first()->amount);
    }

    // --- Loans ----------------------------------------------------------------

    public function test_issuing_a_loan_creates_a_positive_ledger_line(): void
    {
        $partner = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');

        $loan = app(IssuePartnerLoan::class)->handle($partner, '50000.00', '2026-01-15', $this->admin->id);

        $this->assertSame(LoanStatus::Outstanding, $loan->status);
        $this->assertSame('50000.00', $partner->ledgerEntries()->first()->amount);
    }

    public function test_a_partial_repayment_keeps_the_loan_outstanding(): void
    {
        $partner = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $loan = app(IssuePartnerLoan::class)->handle($partner, '50000.00', '2026-01-15', $this->admin->id);

        app(RecordLoanRepayment::class)->handle($loan, '20000.00', '2026-02-01', $this->admin->id);

        $this->assertSame(LoanStatus::Outstanding, $loan->fresh()->status);
    }

    public function test_a_full_repayment_marks_the_loan_repaid(): void
    {
        $partner = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $loan = app(IssuePartnerLoan::class)->handle($partner, '50000.00', '2026-01-15', $this->admin->id);

        app(RecordLoanRepayment::class)->handle($loan, '30000.00', '2026-02-01', $this->admin->id);
        app(RecordLoanRepayment::class)->handle($loan, '20000.00', '2026-03-01', $this->admin->id);

        $this->assertSame(LoanStatus::Repaid, $loan->fresh()->status);
    }

    public function test_a_repayment_exceeding_the_outstanding_balance_is_refused(): void
    {
        $partner = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $loan = app(IssuePartnerLoan::class)->handle($partner, '50000.00', '2026-01-15', $this->admin->id);

        $this->expectException(RepaymentExceedsOutstandingBalanceException::class);

        app(RecordLoanRepayment::class)->handle($loan, '50000.01', '2026-02-01', $this->admin->id);
    }

    public function test_capital_and_loan_balances_never_mix_on_the_partner_ledger(): void
    {
        $partner = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');

        app(RecordCapitalContribution::class)->handle($partner, '100000.00', '2026-01-01', $this->admin->id);
        app(IssuePartnerLoan::class)->handle($partner, '50000.00', '2026-01-15', $this->admin->id);

        $this->assertEquals(100000.00, $partner->capitalEntries()->sum('amount'));
        $this->assertEquals(50000.00, $partner->loans()->sum('principal_amount'));
        $this->assertSame(2, $partner->ledgerEntries()->count());
    }

    // --- Profit allocation ----------------------------------------------------

    public function test_profit_is_split_evenly_across_a_stable_50_50_period(): void
    {
        $a = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $b = app(CreatePartner::class)->handle('Bilal', '2026-01-01');
        app(RecordOwnershipRebalance::class)->handle([$a->id => '50.00', $b->id => '50.00'], '2026-01-01');

        $period = app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');

        $allocations = app(AllocateProfitToPartners::class)->handle($period, '10000.00');

        $this->assertCount(2, $allocations);
        $this->assertSame('5000.00', $allocations->firstWhere('partner_id', $a->id)->allocated_amount);
        $this->assertSame('5000.00', $allocations->firstWhere('partner_id', $b->id)->allocated_amount);
    }

    public function test_profit_is_split_proportionally_when_ownership_changes_mid_period(): void
    {
        $a = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $b = app(CreatePartner::class)->handle('Bilal', '2026-01-01');
        app(RecordOwnershipRebalance::class)->handle([$a->id => '50.00', $b->id => '50.00'], '2026-01-01');

        $period = app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-30');

        // Ownership changes exactly halfway through the 30-day period.
        app(RecordOwnershipRebalance::class)->handle([$a->id => '40.00', $b->id => '60.00'], '2026-01-16');

        $allocations = app(AllocateProfitToPartners::class)->handle($period, '30000.00');

        $this->assertCount(4, $allocations);

        $aFirstHalf = $allocations->first(fn ($row) => $row->partner_id === $a->id && $row->applied_percentage === '50.00');
        $aSecondHalf = $allocations->first(fn ($row) => $row->partner_id === $a->id && $row->applied_percentage === '40.00');

        // 15 days at 50% + 15 days at 40%, of a 30-day, 30000 period:
        // half the period (15000) each half; 50% of 15000 = 7500, 40% of 15000 = 6000.
        $this->assertSame('7500.00', $aFirstHalf->allocated_amount);
        $this->assertSame('6000.00', $aSecondHalf->allocated_amount);
    }

    public function test_allocation_fails_when_no_ownership_data_covers_the_period(): void
    {
        app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $period = app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');

        $this->expectException(NoOwnershipDataForDateException::class);

        app(AllocateProfitToPartners::class)->handle($period, '10000.00');
    }

    // --- Distribution ------------------------------------------------------------

    public function test_a_distribution_creates_a_negative_ledger_line(): void
    {
        $partner = app(CreatePartner::class)->handle('Ahmed', '2026-01-01');
        $period = app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');

        app(RecordPartnerDistribution::class)->handle($partner, $period, '5000.00', $this->bank->id, '2026-02-01', $this->admin->id);

        $this->assertSame('-5000.00', $partner->ledgerEntries()->first()->amount);
        $this->assertSame(1, $partner->distributions()->count());
    }
}
