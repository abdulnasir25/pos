<?php

namespace App\Modules\Access\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission gate: `->middleware('permission:sales.create')`.
 * Runs after 'auth' and 'tenant' — relies on both already having resolved
 * the authenticated user and the tenant connection.
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permissionSlug): Response
    {
        abort_unless(
            $request->user()?->hasPermission($permissionSlug) ?? false,
            403,
            "Missing required permission: {$permissionSlug}",
        );

        return $next($request);
    }
}
