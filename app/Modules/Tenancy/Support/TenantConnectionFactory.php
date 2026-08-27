<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * The only place that knows how a tenant's `database` column turns into a
 * real database connection. Today that's a per-tenant SQLite file;
 * swapping to per-tenant MySQL credentials later is a change confined to
 * this class — TenantResolver, TenantContext, and IdentifyTenant never
 * touch a connection array directly.
 */
class TenantConnectionFactory
{
    public function connectionNameFor(Tenant $tenant): string
    {
        return config('tenancy.tenant_connection', 'tenant');
    }

    /**
     * Point the shared 'tenant' connection at this tenant's database and
     * force a fresh PDO handle — never reuse a connection cached for a
     * previously-resolved tenant on this same worker.
     */
    public function useConnectionFor(Tenant $tenant): string
    {
        $name = $this->connectionNameFor($tenant);

        Config::set("database.connections.{$name}", array_merge(
            config('database.connections.tenant'),
            ['database' => $this->databasePathFor($tenant)],
        ));

        DB::purge($name);

        return $name;
    }

    public function databasePathFor(Tenant $tenant): string
    {
        return config('tenancy.tenant_database_path').DIRECTORY_SEPARATOR.$tenant->database.'.sqlite';
    }
}
