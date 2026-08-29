<?php

namespace App\Modules\Platform\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Landlord-domain counterpart to Access's HandleInertiaRequests — shares
 * the 'landlord' guard's user instead of the tenant-scoped 'web' guard.
 * No roles/permissions here: the landlord side has a single kind of
 * authenticated actor, the platform admin.
 */
class HandleLandlordInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user('landlord');

        return [
            ...parent::share($request),
            'flash' => [
                'success' => $request->session()->get('success'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
            ],
        ];
    }
}
