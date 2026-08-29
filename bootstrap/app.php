<?php

use App\Modules\Access\Http\Middleware\HandleInertiaRequests;
use App\Modules\Access\Http\Middleware\RequirePermission;
use App\Modules\Platform\Console\Commands\TenantCreateCommand;
use App\Modules\Platform\Console\Commands\TenantMigrateCommand;
use App\Modules\Tenancy\Http\Middleware\IdentifyTenant;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['web', 'tenant', HandleInertiaRequests::class])
                ->group(base_path('routes/tenant.php'));
        },
    )
    ->withCommands([
        app_path('Console/Commands'),
        TenantCreateCommand::class,
        TenantMigrateCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => IdentifyTenant::class,
            'permission' => RequirePermission::class,
        ]);

        // Laravel reorders route middleware by its internal priority list,
        // not by the order they're written — the auth-checking contract
        // (which Authenticate implements) is prioritized and would
        // otherwise run before our custom 'tenant' middleware, meaning the
        // auth guard would query the *landlord* connection for a
        // session's user instead of the tenant's. This pins IdentifyTenant
        // to run immediately before it no matter how a route's middleware
        // list is written. Note: the priority list is keyed by the
        // AuthenticatesRequests *contract*, not the concrete Authenticate
        // class — targeting the concrete class here silently no-ops.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: IdentifyTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
