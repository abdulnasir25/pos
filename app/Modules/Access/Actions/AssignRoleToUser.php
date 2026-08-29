<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Models\Role;
use App\Modules\AuditLog\Actions\RecordAuditLog;
use App\Models\User;

class AssignRoleToUser
{
    public function __construct(private readonly RecordAuditLog $auditLog) {}

    public function handle(User $user, Role $role, ?int $performedBy = null): void
    {
        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->auditLog->handle(
            userId: $performedBy,
            action: 'role.assigned',
            auditableType: User::class,
            auditableId: $user->id,
            newValues: ['role' => $role->slug],
        );
    }
}
