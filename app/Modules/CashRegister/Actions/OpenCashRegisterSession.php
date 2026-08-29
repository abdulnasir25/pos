<?php

namespace App\Modules\CashRegister\Actions;

use App\Modules\CashRegister\Enums\CashRegisterSessionStatus;
use App\Modules\CashRegister\Enums\FinancialAccountType;
use App\Modules\CashRegister\Exceptions\AccountNotACashAccountException;
use App\Modules\CashRegister\Exceptions\SessionAlreadyOpenException;
use App\Modules\CashRegister\Models\CashRegisterSession;
use App\Modules\CashRegister\Models\FinancialAccount;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The only supported way to open a session. A financial account can
 * have at most one open session at a time — same BEGIN IMMEDIATE
 * reasoning as CreateFinancialPeriod's overlap check: the "is one
 * already open" read and the insert below must not be separated by
 * another writer, which a plain DB::transaction() does not guarantee
 * on SQLite.
 */
class OpenCashRegisterSession
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(FinancialAccount $financialAccount, int $openedBy, string $openingFloat): CashRegisterSession
    {
        $this->tenant->get();

        if ($financialAccount->account_type !== FinancialAccountType::Cash) {
            throw AccountNotACashAccountException::forAccount($financialAccount->id, $financialAccount->account_type->value);
        }

        $connection = DB::connection(config('tenancy.tenant_connection', 'tenant'));
        $pdo = $connection->getPdo();

        $pdo->exec('BEGIN IMMEDIATE');

        try {
            $alreadyOpen = $connection->table('cash_register_sessions')
                ->where('financial_account_id', $financialAccount->id)
                ->where('status', CashRegisterSessionStatus::Open->value)
                ->exists();

            if ($alreadyOpen) {
                throw SessionAlreadyOpenException::forAccount($financialAccount->id);
            }

            $session = CashRegisterSession::create([
                'financial_account_id' => $financialAccount->id,
                'opened_by' => $openedBy,
                'opening_float' => $openingFloat,
                'status' => CashRegisterSessionStatus::Open,
                'opened_at' => now(),
            ]);

            $pdo->exec('COMMIT');

            return $session;
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');

            throw $e;
        }
    }
}
