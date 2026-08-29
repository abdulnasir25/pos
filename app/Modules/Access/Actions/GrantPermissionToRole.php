<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;

class GrantPermissionToRole
{
    public function handle(Role $role, Permission $permission): void
    {
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }
}
