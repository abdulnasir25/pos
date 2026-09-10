<?php

namespace App\Modules\Access\Actions;

use App\Models\User;
use App\Modules\Access\Exceptions\CannotDeactivateLastSuperAdminException;

/**
 * The "delete" equivalent for a staff login — no login row is ever
 * removed, only disabled. Refuses to deactivate the last active holder
 * of the protected super_admin role, mirroring the guard
 * RemoveRoleFromUser already applies when the role itself is stripped.
 */
class ToggleUserStatus
{
    public function handle(User $user): User
    {
        $activating = ! $user->isActive();

        if (! $activating && $this->isLastActiveSuperAdmin($user)) {
            throw CannotDeactivateLastSuperAdminException::forUser($user->id);
        }

        $user->update(['status' => $activating ? 'active' : 'inactive']);

        return $user;
    }

    private function isLastActiveSuperAdmin(User $user): bool
    {
        if (! $user->hasRole('super_admin')) {
            return false;
        }

        $activeSuperAdmins = User::whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))
            ->where('status', 'active')
            ->count();

        return $activeSuperAdmins <= 1;
    }
}
