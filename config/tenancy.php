<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Central (landlord) domains
    |--------------------------------------------------------------------------
    |
    | Requests to these hosts are never resolved to a tenant — they serve
    | the platform layer itself (super-admin, marketing site, tenant
    | provisioning). Every other host is treated as "<slug>.<one of these>"
    | and resolved via TenantResolver.
    |
    */

    'central_domains' => array_filter(explode(',', env('TENANT_CENTRAL_DOMAINS', 'localhost'))),

    /*
    |--------------------------------------------------------------------------
    | Tenant database storage path
    |--------------------------------------------------------------------------
    |
    | Each tenant's SQLite database file lives here, named after the
    | tenant's `database` column. Swappable for a real server (MySQL/
    | Postgres) later by changing TenantConnectionFactory — the resolver,
    | context, and middleware never need to know which driver a tenant uses.
    |
    */

    'tenant_database_path' => database_path('tenants'),

    /*
    |--------------------------------------------------------------------------
    | MySQL database name prefix
    |--------------------------------------------------------------------------
    |
    | Only applies when TENANT_DB_DRIVER=mysql. A shared MySQL server can
    | easily host databases from several unrelated projects side by side
    | ("acme_prod", "widgetco_tenant", ...) — this prefix keeps every one
    | of this app's tenant schemas visibly grouped and unmistakable in a
    | tool like phpMyAdmin, instead of a bare shop slug that could belong
    | to anything. Never applies to the SQLite path (a per-tenant file
    | living in its own directory needs no such disambiguation).
    |
    */

    'mysql_database_prefix' => env('TENANT_DB_PREFIX', 'ledger_'),

    /*
    |--------------------------------------------------------------------------
    | Connection names
    |--------------------------------------------------------------------------
    */

    'landlord_connection' => 'landlord',
    'tenant_connection' => 'tenant',

    /*
    |--------------------------------------------------------------------------
    | Tenant migrations path
    |--------------------------------------------------------------------------
    */

    'tenant_migrations_path' => database_path('migrations/tenant'),

];
