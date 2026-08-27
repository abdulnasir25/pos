<?php

namespace App\Modules\Tenancy\Database;

/**
 * The tenant-safe query strategy, applied per model: any tenant-scoped
 * model (User today; Products/Sales/Inventory/etc. in later phases) uses
 * this trait instead of hard-coding a connection name. It deliberately
 * does NOT set $connection — leaving it unset means Eloquent resolves the
 * connection dynamically per query from config('database.default'), which
 * is exactly what IdentifyTenant middleware swaps per request. What this
 * trait adds on top is RequireTenantContextScope: a fail-closed guard so
 * a query issued with no tenant resolved throws instead of silently
 * running against whatever connection happened to be default.
 *
 * Laravel auto-invokes boot{TraitName}() for every trait a model uses —
 * see Illuminate\Database\Eloquent\Concerns\HasAttributes::bootTraits().
 */
trait HasTenantScopedQueries
{
    public static function bootHasTenantScopedQueries(): void
    {
        static::addGlobalScope(new RequireTenantContextScope);
    }
}
