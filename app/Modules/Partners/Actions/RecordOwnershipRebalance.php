<?php

namespace App\Modules\Partners\Actions;

use App\Modules\AuditLog\Actions\RecordAuditLog;
use App\Modules\Partners\Exceptions\InvalidOwnershipDateRangeException;
use App\Modules\Partners\Exceptions\OwnershipPercentagesMustSumTo100Exception;
use App\Modules\Partners\Exceptions\RebalanceMustCoverEveryActivePartnerException;
use App\Modules\Tenancy\Support\SerializedWrite;
use App\Modules\Tenancy\Support\TenantContext;
use Carbon\Carbon;

/**
 * The only supported way to change ownership percentages. Takes every
 * active partner's new percentage at once — ownership is a zero-sum
 * relationship between all of them, so a single partner's percentage
 * can never be changed in isolation without risking the sum-to-100
 * invariant. For each partner, closes out whatever ownership period is
 * currently open (effective_to = day before the new effective_from)
 * and opens a new one — the old row's percentage is never edited.
 *
 * Runs through SerializedWrite: the "read the active partner set,
 * validate, then write N rows" sequence is only atomic under
 * concurrency if a second concurrent rebalance can't interleave.
 */
class RecordOwnershipRebalance
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly SerializedWrite $serialized,
        private readonly RecordAuditLog $auditLog,
    ) {}

    /**
     * @param  array<int, string>  $percentagesByPartnerId
     */
    public function handle(array $percentagesByPartnerId, string $effectiveFrom, ?int $performedBy = null): void
    {
        $this->tenant->get();

        [$previousPercentages] = $this->serialized->run(function ($connection) use ($percentagesByPartnerId, $effectiveFrom) {
            $activePartnerIds = $connection->table('partners')
                ->where('status', 'active')
                ->pluck('id')
                ->all();

            $previousPercentages = $connection->table('partner_ownership_periods')
                ->whereIn('partner_id', $activePartnerIds)
                ->whereNull('effective_to')
                ->pluck('percentage', 'partner_id')
                ->all();

            $missing = array_diff($activePartnerIds, array_keys($percentagesByPartnerId));

            if ($missing !== []) {
                throw RebalanceMustCoverEveryActivePartnerException::missing(array_values($missing));
            }

            $sum = '0';
            foreach ($percentagesByPartnerId as $partnerId => $percentage) {
                $sum = bcadd($sum, (string) $percentage, 2);
            }

            if (bccomp($sum, '100', 2) !== 0) {
                throw OwnershipPercentagesMustSumTo100Exception::forSum($sum, $effectiveFrom);
            }

            $newEffectiveFrom = Carbon::parse($effectiveFrom);
            $closeDate = $newEffectiveFrom->clone()->subDay()->toDateString();

            foreach ($percentagesByPartnerId as $partnerId => $percentage) {
                $open = $connection->table('partner_ownership_periods')
                    ->where('partner_id', $partnerId)
                    ->whereNull('effective_to')
                    ->first();

                if ($open !== null) {
                    if ($closeDate < $open->effective_from) {
                        throw InvalidOwnershipDateRangeException::newEffectiveDateBeforeExisting(
                            (int) $partnerId,
                            $effectiveFrom,
                            $open->effective_from,
                        );
                    }

                    $connection->table('partner_ownership_periods')
                        ->where('id', $open->id)
                        ->update(['effective_to' => $closeDate, 'updated_at' => now()]);
                }

                $connection->table('partner_ownership_periods')->insert([
                    'partner_id' => $partnerId,
                    'percentage' => $percentage,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return [$previousPercentages];
        });

        // Written after the transaction commits, and normalized to 2dp
        // explicitly: SQLite's NUMERIC storage can round-trip a
        // raw-query decimal value like '50.00' back as '50' — the audit
        // log's snapshot must stay in the same canonical format
        // regardless of that storage quirk.
        $this->auditLog->handle(
            userId: $performedBy,
            action: 'partner_ownership.rebalanced',
            oldValues: [
                'percentages' => array_map(fn ($p) => bcadd('0', (string) $p, 2), $previousPercentages),
                'effective_from' => $effectiveFrom,
            ],
            newValues: [
                'percentages' => array_map(fn ($p) => bcadd('0', (string) $p, 2), $percentagesByPartnerId),
                'effective_from' => $effectiveFrom,
            ],
        );
    }
}
