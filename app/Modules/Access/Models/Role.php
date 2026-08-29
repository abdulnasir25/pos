<?php

namespace App\Modules\Access\Models;

use App\Models\User;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A tenant-scoped role (no tenant_id column needed — the tenant's own
 * database is the scope). `super_admin` is seeded as protected; see
 * RemoveRoleFromUser for the guard that keeps a tenant from ever losing
 * its last Super Admin.
 */
class Role extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'name',
        'slug',
        'is_protected',
    ];

    protected function casts(): array
    {
        return [
            'is_protected' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }
}
