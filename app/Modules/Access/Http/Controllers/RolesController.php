<?php

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Access\Actions\CreateRole;
use App\Modules\Access\Actions\GrantPermissionToRole;
use App\Modules\Access\Actions\RevokePermissionFromRole;
use App\Modules\Access\Exceptions\DuplicateRoleSlugException;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RolesController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        $roles = Role::with('permissions:id,slug')
            ->orderByDesc('is_protected')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'is_protected' => $role->is_protected,
                'permission_ids' => $role->permissions->pluck('id'),
            ]);

        return Inertia::render('Access/Roles/Index', [
            'roles' => $roles,
            'permissions' => Permission::orderBy('slug')->get(['id', 'slug', 'description']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        try {
            app(CreateRole::class)->handle($validated['name'], Str::slug($validated['name'], '_'));
        } catch (DuplicateRoleSlugException $e) {
            return back()->withErrors(['role' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Role created.');
    }

    /**
     * The protected super_admin role always carries every permission —
     * toggling would let a tenant accidentally cripple the one role
     * every other guard (CannotRemoveLastSuperAdminException,
     * CannotDeactivateLastSuperAdminException) assumes stays fully
     * capable.
     */
    public function togglePermission(Role $role, Permission $permission): RedirectResponse
    {
        if ($role->is_protected) {
            return back()->withErrors(['role' => 'The Super Admin role always has every permission and cannot be changed.']);
        }

        $role->permissions()->where('permissions.id', $permission->id)->exists()
            ? app(RevokePermissionFromRole::class)->handle($role, $permission)
            : app(GrantPermissionToRole::class)->handle($role, $permission);

        return back()->with('success', 'Role permissions updated.');
    }
}
