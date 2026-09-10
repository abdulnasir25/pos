<?php

namespace App\Modules\Access\Http\Controllers;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Actions\CreateUser;
use App\Modules\Access\Actions\RemoveRoleFromUser;
use App\Modules\Access\Actions\ToggleUserStatus;
use App\Modules\Access\Exceptions\CannotDeactivateLastSuperAdminException;
use App\Modules\Access\Exceptions\CannotRemoveLastSuperAdminException;
use App\Modules\Access\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        $users = User::with('roles:id,name,slug')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'status'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'roles' => $user->roles->map(fn (Role $role) => ['id' => $role->id, 'name' => $role->name])->values(),
            ]);

        return Inertia::render('Access/Users/Index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        $user = app(CreateUser::class)->handle($validated['name'], $validated['email'], $validated['password']);

        if (! empty($validated['role_id'])) {
            app(AssignRoleToUser::class)->handle($user, Role::findOrFail($validated['role_id']), $request->user()->id);
        }

        return back()->with('success', 'Staff account created.');
    }

    /**
     * Password is optional here — leaving it blank keeps the current
     * one, filling it in resets it. Folded into the same form as
     * name/email so fixing a typo and resetting a forgotten password
     * don't need two separate flows.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            ...(! empty($validated['password']) ? ['password' => $validated['password']] : []),
        ]);

        return back()->with('success', 'Account updated.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        try {
            app(ToggleUserStatus::class)->handle($user);
        } catch (CannotDeactivateLastSuperAdminException $e) {
            return back()->withErrors(['user' => $e->getMessage()]);
        }

        return back()->with('success', 'Account status updated.');
    }

    public function assignRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        app(AssignRoleToUser::class)->handle($user, Role::findOrFail($validated['role_id']), $request->user()->id);

        return back()->with('success', 'Role assigned.');
    }

    public function removeRole(Request $request, User $user, Role $role): RedirectResponse
    {
        try {
            app(RemoveRoleFromUser::class)->handle($user, $role, $request->user()->id);
        } catch (CannotRemoveLastSuperAdminException $e) {
            return back()->withErrors(['user' => $e->getMessage()]);
        }

        return back()->with('success', 'Role removed.');
    }
}
