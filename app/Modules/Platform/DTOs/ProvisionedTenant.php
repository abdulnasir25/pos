<?php

namespace App\Modules\Platform\DTOs;

use App\Modules\Platform\Models\Tenant;

/**
 * ownerEmail/ownerPassword are null when ProvisionTenant was called
 * with no owner details — a bare tenant with no login, e.g. tooling
 * that just needs an isolated database to test against. The generated
 * password only ever exists in memory for the one request that
 * provisioned the tenant — it's hashed before the User row is written
 * and never stored or logged in plain text anywhere. The caller
 * (console command output, landlord flash message) is the only place
 * it's shown, once.
 */
final readonly class ProvisionedTenant
{
    public function __construct(
        public Tenant $tenant,
        public ?string $ownerEmail = null,
        public ?string $ownerPassword = null,
    ) {}
}
