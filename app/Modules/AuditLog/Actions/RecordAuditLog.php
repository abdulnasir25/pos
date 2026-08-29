<?php

namespace App\Modules\AuditLog\Actions;

use App\Modules\AuditLog\Models\AuditLog;

/**
 * The only supported way to write an audit log entry. Deliberately
 * takes every value as an explicit argument rather than reaching into
 * auth()/request() itself — Actions in this codebase stay decoupled
 * from HTTP context, so a caller (a controller, a console command, a
 * queued job) supplies whatever it actually has.
 */
class RecordAuditLog
{
    public function handle(
        ?int $userId,
        string $action,
        ?string $auditableType = null,
        ?int $auditableId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ipAddress = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ipAddress,
        ]);
    }
}
