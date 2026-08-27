<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Exceptions\NoTenantResolvedException;

/**
 * The single place the rest of the application asks "which tenant is this
 * request for?". Bound as a singleton and populated once, by
 * IdentifyTenant, before any controller runs. Nothing outside the
 * Tenancy module should resolve a tenant any other way.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): Tenant
    {
        return $this->tenant ?? throw NoTenantResolvedException::make();
    }

    public function has(): bool
    {
        return $this->tenant instanceof Tenant;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
