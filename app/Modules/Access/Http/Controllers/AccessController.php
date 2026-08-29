<?php

namespace App\Modules\Access\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The logged-in user's own roles and permissions — split out of the
 * Dashboard, which now leads with business summary data instead.
 * Reads entirely from the auth.user props HandleInertiaRequests
 * already shares on every request, so there is nothing to pass here.
 */
class AccessController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        return Inertia::render('Access/Index');
    }
}
