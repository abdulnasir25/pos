<?php

namespace App\Modules\AuditLog\Support;

use App\Modules\AuditLog\Models\AuditLog;
use Illuminate\Database\Eloquent\Collection;

class GetAuditTrailFor
{
    /**
     * @return Collection<int, AuditLog>
     */
    public function handle(string $auditableType, int $auditableId): Collection
    {
        // Ordered by id, not created_at: two entries written within the
        // same second have identical created_at (second precision), so
        // ordering by it alone doesn't reliably reflect insertion order.
        return AuditLog::where('auditable_type', $auditableType)
            ->where('auditable_id', $auditableId)
            ->orderByDesc('id')
            ->get();
    }
}
