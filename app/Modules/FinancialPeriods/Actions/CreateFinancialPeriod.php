<?php

namespace App\Modules\FinancialPeriods\Actions;

use App\Modules\FinancialPeriods\Enums\FinancialPeriodStatus;
use App\Modules\FinancialPeriods\Exceptions\InvalidPeriodRangeException;
use App\Modules\FinancialPeriods\Exceptions\OverlappingPeriodException;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The only supported way to create a Financial Period. Any date range is
 * accepted — this module does not assume "monthly." A tenant-wide
 * cadence setting, if wanted later, is a separate concern from this
 * table's shape.
 *
 * Overlap detection cannot be expressed as a plain unique constraint —
 * two ranges can share no boundary in common and still overlap — so it's
 * an explicit "does anything already occupy this range" check before the
 * insert. A check-then-insert is only actually safe under concurrency if
 * the check and the insert can't be separated by another writer, which a
 * plain DB::transaction() does NOT guarantee on SQLite: its default
 * deferred BEGIN only takes the write lock at the first write statement,
 * leaving a window where two concurrent calls both run the read below
 * before either has written — the identical race already found and
 * fixed for inventory stock-out (see PostInventoryMovement). BEGIN
 * IMMEDIATE closes that window by taking SQLite's write lock up front,
 * so a second concurrent call blocks here until the first fully commits
 * or rolls back.
 */
class CreateFinancialPeriod
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(string $periodStart, string $periodEnd): FinancialPeriod
    {
        // This action talks to the connection via raw DB::table()/PDO
        // calls (necessarily, for the BEGIN IMMEDIATE below) rather than
        // through Eloquent — which means HasTenantScopedQueries' guard
        // never runs here, since that guard only fires on Eloquent
        // queries. Check explicitly instead of assuming the model layer
        // covers it.
        $this->tenant->get();

        if ($periodEnd < $periodStart) {
            throw InvalidPeriodRangeException::endBeforeStart($periodStart, $periodEnd);
        }

        $connection = DB::connection(config('tenancy.tenant_connection', 'tenant'));
        $pdo = $connection->getPdo();

        $pdo->exec('BEGIN IMMEDIATE');

        try {
            $overlaps = $connection->table('financial_periods')
                ->where('period_start', '<=', $periodEnd)
                ->where('period_end', '>=', $periodStart)
                ->exists();

            if ($overlaps) {
                throw OverlappingPeriodException::forRange($periodStart, $periodEnd);
            }

            $period = FinancialPeriod::create([
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => FinancialPeriodStatus::Open,
            ]);

            $pdo->exec('COMMIT');

            return $period;
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');

            throw $e;
        }
    }
}
