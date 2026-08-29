<?php

namespace App\Modules\FinancialPeriods\Actions;

use App\Modules\FinancialPeriods\Enums\FinancialPeriodStatus;
use App\Modules\FinancialPeriods\Exceptions\InvalidPeriodRangeException;
use App\Modules\FinancialPeriods\Exceptions\OverlappingPeriodException;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use App\Modules\Tenancy\Support\SerializedWrite;
use App\Modules\Tenancy\Support\TenantContext;

/**
 * The only supported way to create a Financial Period. Any date range is
 * accepted — this module does not assume "monthly." A tenant-wide
 * cadence setting, if wanted later, is a separate concern from this
 * table's shape.
 *
 * Overlap detection cannot be expressed as a plain unique constraint —
 * two ranges can share no boundary in common and still overlap — so it's
 * an explicit "does anything already occupy this range" check before the
 * insert, run through SerializedWrite for real cross-writer isolation
 * (see that class for why a plain transaction isn't enough, on either
 * SQLite or MySQL).
 */
class CreateFinancialPeriod
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly SerializedWrite $serialized,
    ) {}

    public function handle(string $periodStart, string $periodEnd): FinancialPeriod
    {
        // This action talks to the connection via raw DB::table() calls
        // rather than through Eloquent — which means
        // HasTenantScopedQueries' guard never runs here, since that
        // guard only fires on Eloquent queries. Check explicitly instead
        // of assuming the model layer covers it.
        $this->tenant->get();

        if ($periodEnd < $periodStart) {
            throw InvalidPeriodRangeException::endBeforeStart($periodStart, $periodEnd);
        }

        return $this->serialized->run(function ($connection) use ($periodStart, $periodEnd) {
            // whereDate(), not where(): Eloquent's 'date' cast writes a
            // full "Y-m-d H:i:s" value (see FinancialPeriod::casts()),
            // so a plain string '<=' / '>=' against a bare 'Y-m-d'
            // argument silently misses the boundary case where one
            // period's start/end date exactly equals another's — e.g.
            // stored "2026-01-31 00:00:00" is NOT <= plain "2026-01-31"
            // as a string, even though the dates are equal. whereDate()
            // wraps the column in SQL DATE(...) so only the calendar
            // date is compared, matching how the two values are meant
            // to be read. Same root cause as the ResolveSalaryForDate
            // bug fixed earlier in the Employees module.
            $overlaps = $connection->table('financial_periods')
                ->whereDate('period_start', '<=', $periodEnd)
                ->whereDate('period_end', '>=', $periodStart)
                ->exists();

            if ($overlaps) {
                throw OverlappingPeriodException::forRange($periodStart, $periodEnd);
            }

            return FinancialPeriod::create([
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => FinancialPeriodStatus::Open,
            ]);
        });
    }
}
