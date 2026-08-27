<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Exceptions\TenantSuspendedException;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use App\Modules\Tenancy\Support\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs before any tenant-scoped route handler. Resolves the tenant,
 * refuses the request outright if it's missing or suspended, switches the
 * default DB connection to that tenant's own database, and binds
 * TenantContext so every downstream service reads the tenant from one
 * place instead of re-deriving it from the request.
 *
 * This is the tenant isolation boundary — no controller, policy, or job
 * should identify a tenant any other way.
 */
class IdentifyTenant
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly TenantConnectionFactory $connections,
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolveOrFail($request);

        if ($tenant->isSuspended()) {
            throw TenantSuspendedException::forSlug($tenant->slug);
        }

        $connectionName = $this->connections->useConnectionFor($tenant);

        // Every Eloquent call made during this request that doesn't name
        // a connection explicitly now resolves against this tenant's
        // database — including models loaded by other middleware further
        // down the pipeline (e.g. auth).
        Config::set('database.default', $connectionName);

        $this->context->set($tenant);

        return $next($request);
    }

    /**
     * Runs after the response has been sent. Restores the default
     * connection to the landlord DB so a reused worker process (PHP-FPM,
     * Octane) never starts its next request — for a different tenant, or
     * for the landlord itself — still pointed at this request's tenant.
     */
    public function terminate(Request $request, Response $response): void
    {
        Config::set('database.default', config('tenancy.landlord_connection', 'landlord'));

        $this->context->clear();
    }
}
