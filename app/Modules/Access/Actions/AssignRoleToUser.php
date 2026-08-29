<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Models\Role;
use App\Models\User;

class AssignRoleToUser
{
    public function handle(User $user, Role $role): void
    {
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
