<?php

namespace App\Modules\Platform\Console\Commands;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use Throwable;

/**
 * The only supported way to provision a tenant: create the landlord row,
 * create its database (a SQLite file, or a MySQL schema — whichever
 * TENANT_DB_DRIVER selects), run every tenant migration against it. Runs
 * as one logical unit — if migration fails, the half-created tenant is
 * torn back down rather than left in a broken "provisioning" state.
 */
class TenantCreateCommand extends Command
{
    protected $signature = 'tenants:create
        {name : Display name of the business, e.g. "Al-Fateh Cloth House"}
        {--slug= : Subdomain slug; derived from the name if omitted}';

    protected $description = 'Provision a new tenant: landlord record, database, and schema.';

    public function handle(TenantConnectionFactory $connections): int
    {
        $name = $this->argument('name');
        $slug = $this->option('slug') ?: Str::slug($name);

        if (Tenant::where('slug', $slug)->exists()) {
            $this->error("A tenant with slug [{$slug}] already exists.");

            return self::FAILURE;
        }

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'database' => $slug,
            'status' => 'provisioning',
        ]);

        try {
            $connections->usesMysql()
                ? $this->createMysqlDatabase($slug)
                : $this->createSqliteFile($connections->databasePathFor($tenant));

            $connectionName = $connections->useConnectionFor($tenant);

            Artisan::call('migrate', [
                '--database' => $connectionName,
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
                '--force' => true,
            ], $this->output);

            $tenant->update(['status' => 'active']);
        } catch (Throwable $e) {
            $this->error("Provisioning failed, rolling back: {$e->getMessage()}");

            $connections->usesMysql()
                ? $this->dropMysqlDatabase($slug)
                : File::delete($connections->databasePathFor($tenant));

            $tenant->delete();

            return self::FAILURE;
        }

        $this->info("Tenant [{$slug}] provisioned and active.");

        return self::SUCCESS;
    }

    private function createSqliteFile(string $path): void
    {
        File::ensureDirectoryExists(dirname($path));

        if (! File::exists($path)) {
            File::put($path, '');
        }
    }

    /**
     * A tenant's schema doesn't exist yet, so this can't go through the
     * 'tenant' Laravel connection (which needs a database name to
     * connect at all) — a bare administrative PDO connection to the
     * server itself is the only way to run CREATE/DROP DATABASE.
     */
    private function administrativePdo(): PDO
    {
        $config = config('database.connections.tenant');

        return new PDO(
            "mysql:host={$config['host']};port={$config['port']}",
            $config['username'],
            $config['password'],
        );
    }

    private function createMysqlDatabase(string $name): void
    {
        $this->administrativePdo()->exec(
            "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    private function dropMysqlDatabase(string $name): void
    {
        $this->administrativePdo()->exec("DROP DATABASE IF EXISTS `{$name}`");
    }
}
