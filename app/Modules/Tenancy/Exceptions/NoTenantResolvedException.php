<?php

namespace App\Modules\Tenancy\Exceptions;

use RuntimeException;

/**
 * Thrown by TenantContext::get() and by any HasTenantScopedQueries model
 * when code tries to reach tenant data without a tenant having been
 * resolved first — e.g. a queued job that forgot to bind its own tenant
 * connection before touching a model. Fails loud instead of silently
 * running against whatever connection happened to be default.
 */
class NoTenantResolvedException extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'No tenant has been resolved for this request or job. '
            .'Did it pass through the IdentifyTenant middleware, or — for '
            .'queued work — call TenantConnectionFactory::useConnectionFor() explicitly?'
        );
    }
}
