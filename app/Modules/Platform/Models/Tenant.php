<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lives only in the landlord database. Never carries business data —
 * that all lives inside the tenant's own database, addressed by
 * TenantConnectionFactory using the `database` column below.
 */
class Tenant extends Model
{
    protected $connection = 'landlord';

    protected $fillable = [
        'name',
        'slug',
        'database',
        'status',
        'suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
