<?php

namespace App\Modules\Platform\Console\Commands;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Applies pending tenant migrations to one tenant (--tenant=slug) or every
 * active tenant. Each tenant is migrated inside its own connection, one
 * at a time — a failure on one tenant's database must never block or
 * silently skip the rest.
 */
class TenantMigrateCommand extends Command
{
    protected $signature = 'tenants:migrate
        {--tenant= : Slug of a single tenant to migrate; omit to migrate every active tenant}';

    protected $description = 'Run pending tenant-schema migrations against one or all tenant databases.';

    public function handle(TenantConnectionFactory $connections): int
    {
        $tenants = $this->option('tenant')
            ? Tenant::where('slug', $this->option('tenant'))->get()
            : Tenant::where('status', 'active')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No matching tenants found.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($tenants as $tenant) {
            $this->line("Migrating tenant [{$tenant->slug}] ...");

            $connectionName = $connections->useConnectionFor($tenant);

            $exitCode = Artisan::call('migrate', [
                '--database' => $connectionName,
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
                '--force' => true,
            ], $this->output);

            if ($exitCode !== self::SUCCESS) {
                $failures++;
                $this->error("Migration failed for tenant [{$tenant->slug}].");
            }
        }

        if ($failures > 0) {
            $this->error("{$failures} tenant(s) failed to migrate.");

            return self::FAILURE;
        }

        $this->info('All tenants migrated.');

        return self::SUCCESS;
    }
}
