<?php

namespace App\Modules\AuditLog\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only. No update or delete route exists anywhere in this
 * module — see Actions/RecordAuditLog, the only supported way to
 * write one. No updated_at column: a log entry is written once and
 * never touched again.
 */
class AuditLog extends Model
{
    use HasTenantScopedQueries;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
