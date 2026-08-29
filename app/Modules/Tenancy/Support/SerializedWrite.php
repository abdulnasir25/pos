<?php

namespace App\Modules\Tenancy\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs a "read-then-write, must not race" sequence with real
 * cross-writer isolation — whichever driver the tenant connection
 * actually uses today. Every Action in this codebase that does an
 * overlap/uniqueness check before an insert (CreateFinancialPeriod,
 * RecordSalaryChange, OpenCashRegisterSession, RecordOwnershipRebalance)
 * needs this, for the identical reason: a plain DB::transaction() does
 * not guarantee the check and the write can't be separated by another
 * concurrent writer.
 *
 * SQLite: BEGIN IMMEDIATE takes the write lock up front, closing the
 * TOCTOU window a default deferred transaction leaves open.
 *
 * MySQL/MariaDB: there's no equivalent "take the lock before reading"
 * primitive, so SERIALIZABLE isolation is used instead — it makes the
 * same race impossible a different way, by forcing one of two
 * conflicting concurrent transactions to fail outright (a deadlock or
 * serialization-failure error) rather than silently interleave.
 * Laravel's Connection::transaction() already retries automatically
 * when it detects exactly that failure, so the caller doesn't need to.
 */
class SerializedWrite
{
    public function run(callable $callback): mixed
    {
        $connection = DB::connection(config('tenancy.tenant_connection', 'tenant'));

        if ($connection->getDriverName() === 'sqlite') {
            return $this->runSqlite($connection, $callback);
        }

        return $this->runSerializableIsolation($connection, $callback);
    }

    private function runSqlite(Connection $connection, callable $callback): mixed
    {
        $pdo = $connection->getPdo();

        $pdo->exec('BEGIN IMMEDIATE');

        try {
            $result = $callback($connection);

            $pdo->exec('COMMIT');

            return $result;
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');

            throw $e;
        }
    }

    private function runSerializableIsolation(Connection $connection, callable $callback): mixed
    {
        // Isolation level applies only to the transaction started next,
        // so it must be set immediately before, outside any transaction.
        $connection->statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');

        return $connection->transaction(fn () => $callback($connection), attempts: 3);
    }
}
