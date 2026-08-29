<?php

namespace App\Modules\Access\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Runs after 'tenant' in the tenant route group, so auth()->user() here
 * already resolves against the tenant's own database. Shares the
 * authenticated user's roles/permissions on every Inertia page so the
 * frontend can gate UI without a round-trip.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles()->pluck('slug'),
                    'permissions' => $user->roles()
                        ->with('permissions:id,slug')
                        ->get()
                        ->pluck('permissions.*.slug')
                        ->flatten()
                        ->unique()
                        ->values(),
                ] : null,
            ],
        ];
    }
}
