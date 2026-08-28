<?php

namespace App\Modules\FinancialPeriods\Actions;

use App\Modules\FinancialPeriods\Enums\FinancialPeriodStatus;
use App\Modules\FinancialPeriods\Exceptions\InvalidPeriodTransitionException;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * The single write path every lifecycle-transition action funnels
 * through — mirrors Inventory's PostInventoryMovement: one core action
 * owning the atomic guard, wrapped by verb-specific actions
 * (StartCalculation, MoveToReview, ClosePeriod) that callers actually
 * use.
 *
 * The transition check and the write happen in the same SQL statement
 * (an UPDATE ... WHERE status = ?), not as a separate read-then-write —
 * the same reasoning that makes Inventory's stock-out safe under
 * concurrency applies here: two concurrent attempts to transition the
 * same period can't both succeed, because the second one's WHERE clause
 * no longer matches once the first has committed.
 */
class ApplyPeriodTransition
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(FinancialPeriod $period, FinancialPeriodStatus $to, array $extraAttributes = []): FinancialPeriod
    {
        // Same explicit check as CreateFinancialPeriod, for the same
        // reason: the write below goes through DB::table(), which
        // bypasses HasTenantScopedQueries' Eloquent-only guard.
        $this->tenant->get();

        $from = $period->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidPeriodTransitionException::forAttempt($period->id, $from, $to);
        }

        $connection = DB::connection(config('tenancy.tenant_connection', 'tenant'));

        return $connection->transaction(function () use ($connection, $period, $from, $to, $extraAttributes) {
            $affected = $connection->table('financial_periods')
                ->where('id', $period->id)
                ->where('status', $from->value)
                ->update(array_merge($extraAttributes, [
                    'status' => $to->value,
                    'updated_at' => now(),
                ]));

            if ($affected === 0) {
                // Someone else transitioned this period between our read
                // and this statement — re-fetch to report the real
                // current state rather than the stale one we started with.
                $current = FinancialPeriod::findOrFail($period->id);

                throw InvalidPeriodTransitionException::forAttempt($period->id, $current->status, $to);
            }

            return $period->fresh();
        });
    }
}
