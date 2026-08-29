<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Exceptions\CannotRemoveLastSuperAdminException;
use App\Modules\Access\Models\Role;
use App\Models\User;

/**
 * The only supported way to strip a role from a user. Refuses when the
 * role is protected (super_admin) and this user is the last one who
 * holds it — a tenant must always keep at least one Super Admin.
 */
class RemoveRoleFromUser
{
    public function handle(User $user, Role $role): void
    {
        if ($role->is_protected && $this->isLastHolder($user, $role)) {
            throw CannotRemoveLastSuperAdminException::forUser($user->id);
        }

        $user->roles()->detach($role->id);
    }

    private function isLastHolder(User $user, Role $role): bool
    {
        $holderCount = $role->users()->count();

        return $holderCount <= 1 && $role->users()->where('users.id', $user->id)->exists();
    }
}
