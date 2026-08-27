<?php

namespace App\Modules\Tenancy\Database;

use App\Modules\Tenancy\Exceptions\NoTenantResolvedException;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Registered by HasTenantScopedQueries on every model that uses it. Not a
 * row filter — database-per-tenant already makes cross-tenant rows
 * physically unreachable. This scope's only job is to refuse to run at
 * all if no tenant has been resolved yet, so a bug like "a queued job
 * forgot to bind its tenant connection" fails loudly and immediately
 * instead of silently querying whatever connection happened to be
 * default.
 */
class RequireTenantContextScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app(TenantContext::class)->has()) {
            throw NoTenantResolvedException::make();
        }
    }
}
