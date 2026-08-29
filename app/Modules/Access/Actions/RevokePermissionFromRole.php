<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;

class RevokePermissionFromRole
{
    public function handle(Role $role, Permission $permission): void
    {
        $role->permissions()->detach($permission->id);
    }
}
