<?php

namespace Tests\Feature\Employees;

use App\Models\User;
use App\Modules\Employees\Actions\CreateEmployee;
use App\Modules\Employees\Actions\RecordSalaryAccrual;
use App\Modules\Employees\Actions\RecordSalaryChange;
use App\Modules\Employees\Actions\RecordSalaryPayment;
use App\Modules\Employees\Enums\EmployeeLedgerEntryType;
use App\Modules\Employees\Exceptions\DuplicateSalaryAccrualException;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\EmployeeLedgerEntry;
use App\Modules\Employees\Models\SalaryPayment;
use App\Modules\FinancialPeriods\Actions\CreateFinancialPeriod;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SalaryPaymentTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;
    private Employee $employee;
    private FinancialPeriod $period;
    private PaymentMethod $cash;
    private User $actor;

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

        $this->employee = app(CreateEmployee::class)->handle('Tailor Ahmed', '2026-01-01');
        app(RecordSalaryChange::class)->handle($this->employee, '50000.00', '2026-01-01');
        $this->period = app(CreateFinancialPeriod::class)->handle('2026-01-01', '2026-01-31');
        $this->cash = PaymentMethod::create(['name' => 'Cash']);
        $this->actor = User::create(['name' => 'Manager', 'email' => 'manager@alfateh.test', 'password' => bcrypt('secret')]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tenantDbPath);
        app(TenantContext::class)->clear();
        config(['database.default' => 'landlord']);

        parent::tearDown();
    }

    public function test_a_valid_salary_payment_is_recorded(): void
    {
        $payment = app(RecordSalaryPayment::class)->handle($this->employee, $this->period, '50000.00', $this->cash->id, $this->actor->id);

        $this->assertSame('50000.00', $payment->amount);
        $this->assertSame($this->cash->id, $payment->payment_method_id);
        $this->assertSame($this->actor->id, $payment->created_by);
    }

    public function test_the_payment_is_attached_to_the_financial_period(): void
    {
        $payment = app(RecordSalaryPayment::class)->handle($this->employee, $this->period, '50000.00', $this->cash->id, $this->actor->id);

        $this->assertSame($this->period->id, $payment->financial_period_id);
    }

    public function test_the_payment_creates_a_matching_ledger_entry(): void
    {
        $payment = app(RecordSalaryPayment::class)->handle($this->employee, $this->period, '50000.00', $this->cash->id, $this->actor->id);

        $entry = EmployeeLedgerEntry::where('employee_id', $this->employee->id)->first();

        $this->assertSame(EmployeeLedgerEntryType::SalaryPayment, $entry->entry_type);
        $this->assertSame('50000.00', $entry->amount);
        $this->assertSame($this->period->id, $entry->financial_period_id);
        $this->assertSame(SalaryPayment::class, $entry->reference_type);
        $this->assertSame($payment->id, $entry->reference_id);
    }

    public function test_multiple_payments_do_not_overwrite_each_other(): void
    {
        app(RecordSalaryPayment::class)->handle($this->employee, $this->period, '25000.00', $this->cash->id, $this->actor->id);
        app(RecordSalaryPayment::class)->handle($this->employee, $this->period, '25000.00', $this->cash->id, $this->actor->id);

        $this->assertSame(2, SalaryPayment::count());
        $this->assertSame(2, EmployeeLedgerEntry::count());
    }

    public function test_tenant_a_cannot_see_tenant_bs_salary_payments(): void
    {
        app(RecordSalaryPayment::class)->handle($this->employee, $this->period, '50000.00', $this->cash->id, $this->actor->id);

        $tenantB = Tenant::create(['name' => 'Zainab Fabrics', 'slug' => 'zainab', 'database' => 'zainab', 'status' => 'active']);
        File::put($this->tenantDbPath.'/zainab.sqlite', '');
        app(TenantConnectionFactory::class)->useConnectionFor($tenantB);
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--realpath' => false, '--force' => true]);
        app(TenantContext::class)->set($tenantB);

        $this->assertSame(0, SalaryPayment::count());
    }

    // --- Salary accrual (ledger foundation) ---------------------------------

    public function test_salary_accrual_records_the_resolved_salary_for_the_period(): void
    {
        $entry = app(RecordSalaryAccrual::class)->handle($this->employee, $this->period);

        $this->assertSame(EmployeeLedgerEntryType::SalaryAccrual, $entry->entry_type);
        $this->assertSame('50000.00', $entry->amount);
        $this->assertSame($this->employee->id, $entry->employee_id);
        $this->assertSame($this->period->id, $entry->financial_period_id);
    }

    public function test_salary_accrual_cannot_be_recorded_twice_for_the_same_period(): void
    {
        app(RecordSalaryAccrual::class)->handle($this->employee, $this->period);

        $this->expectException(DuplicateSalaryAccrualException::class);
        app(RecordSalaryAccrual::class)->handle($this->employee, $this->period);
    }

    public function test_accrual_and_payment_are_independent_ledger_entries(): void
    {
        app(RecordSalaryAccrual::class)->handle($this->employee, $this->period);
        app(RecordSalaryPayment::class)->handle($this->employee, $this->period, '50000.00', $this->cash->id, $this->actor->id);

        $this->assertSame(2, EmployeeLedgerEntry::where('employee_id', $this->employee->id)->count());
        $this->assertSame(1, EmployeeLedgerEntry::where('entry_type', EmployeeLedgerEntryType::SalaryAccrual)->count());
        $this->assertSame(1, EmployeeLedgerEntry::where('entry_type', EmployeeLedgerEntryType::SalaryPayment)->count());
    }
}
