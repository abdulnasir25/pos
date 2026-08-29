<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * The only place that knows how a tenant's `database` column turns into a
 * real database connection. Driver-agnostic by design: the 'tenant'
 * template in config/database.php already carries either a SQLite or a
 * MySQL shape (switched once per environment via TENANT_DB_DRIVER) —
 * this class only ever fills in the one thing that's actually per-tenant,
 * the database name/file, never anything driver-specific. TenantResolver,
 * TenantContext, and IdentifyTenant never touch a connection array
 * directly.
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
            ['database' => $this->databaseNameFor($tenant)],
        ));

        DB::purge($name);

        return $name;
    }

    /**
     * The MySQL schema name, or the SQLite file's basename (without
     * extension) — either way, the value TenantCreateCommand stored in
     * tenants.database at provisioning time.
     */
    public function databaseNameFor(Tenant $tenant): string
    {
        return $this->usesMysql()
            ? $tenant->database
            : $this->databasePathFor($tenant);
    }

    public function usesMysql(): bool
    {
        return config('database.connections.tenant.driver') === 'mysql';
    }

    public function databasePathFor(Tenant $tenant): string
    {
        return config('tenancy.tenant_database_path').DIRECTORY_SEPARATOR.$tenant->database.'.sqlite';
    }
}
