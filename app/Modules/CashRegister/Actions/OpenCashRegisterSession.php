<?php

namespace App\Modules\CashRegister\Actions;

use App\Modules\CashRegister\Enums\CashRegisterSessionStatus;
use App\Modules\CashRegister\Enums\FinancialAccountType;
use App\Modules\CashRegister\Exceptions\AccountNotACashAccountException;
use App\Modules\CashRegister\Exceptions\SessionAlreadyOpenException;
use App\Modules\CashRegister\Models\CashRegisterSession;
use App\Modules\CashRegister\Models\FinancialAccount;
use App\Modules\Tenancy\Support\SerializedWrite;
use App\Modules\Tenancy\Support\TenantContext;

/**
 * The only supported way to open a session. A financial account can
 * have at most one open session at a time — the "is one already open"
 * read and the insert below run through SerializedWrite so a second
 * concurrent call can't interleave with the first.
 */
class OpenCashRegisterSession
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly SerializedWrite $serialized,
    ) {}

    public function handle(FinancialAccount $financialAccount, int $openedBy, string $openingFloat): CashRegisterSession
    {
        $this->tenant->get();

        if ($financialAccount->account_type !== FinancialAccountType::Cash) {
            throw AccountNotACashAccountException::forAccount($financialAccount->id, $financialAccount->account_type->value);
        }

        return $this->serialized->run(function ($connection) use ($financialAccount, $openedBy, $openingFloat) {
            $alreadyOpen = $connection->table('cash_register_sessions')
                ->where('financial_account_id', $financialAccount->id)
                ->where('status', CashRegisterSessionStatus::Open->value)
                ->exists();

            if ($alreadyOpen) {
                throw SessionAlreadyOpenException::forAccount($financialAccount->id);
            }

            return CashRegisterSession::create([
                'financial_account_id' => $financialAccount->id,
                'opened_by' => $openedBy,
                'opening_float' => $openingFloat,
                'status' => CashRegisterSessionStatus::Open,
                'opened_at' => now(),
            ]);
        });
    }
}
