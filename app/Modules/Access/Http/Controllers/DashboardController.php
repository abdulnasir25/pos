<?php

namespace App\Modules\Access\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        return Inertia::render('Dashboard');
    }
}
