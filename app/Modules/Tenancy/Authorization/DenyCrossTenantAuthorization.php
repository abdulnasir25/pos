<?php

namespace App\Modules\Tenancy\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Registered once, globally, as a Gate::before() callback (see
 * TenancyServiceProvider). Runs before any specific policy method for any
 * future module. If the object being authorized is an Eloquent model
 * bound to a connection, and that connection doesn't match the acting
 * user's own connection, the check is denied outright — no policy has to
 * remember to add this itself.
 *
 * This is a backstop, not the authorization system: Roles & Permissions
 * (Phase 7) still owns what a user is allowed to do. This only guarantees
 * that whatever a policy decides, it can never be decided in the acting
 * user's favor against a record loaded from a different tenant.
 */
class DenyCrossTenantAuthorization
{
    public function __invoke(Authenticatable $user, string $ability, array $arguments = []): ?bool
    {
        foreach ($arguments as $argument) {
            if (! $argument instanceof Model) {
                continue;
            }

            $modelConnection = $argument->getConnectionName();
            $userConnection = $user instanceof Model ? $user->getConnectionName() : null;

            if ($modelConnection !== null && $modelConnection !== $userConnection) {
                return false;
            }
        }

        return null;
    }
}
