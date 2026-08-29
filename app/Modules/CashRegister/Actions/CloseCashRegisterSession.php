<?php

namespace App\Modules\CashRegister\Actions;

use App\Modules\CashRegister\Enums\CashRegisterSessionStatus;
use App\Modules\CashRegister\Exceptions\SessionAlreadyClosedException;
use App\Modules\CashRegister\Models\CashRegisterSession;

class CloseCashRegisterSession
{
    public function handle(CashRegisterSession $session, int $closedBy, string $countedClosing): CashRegisterSession
    {
        if ($session->status === CashRegisterSessionStatus::Closed) {
            throw SessionAlreadyClosedException::forSession($session->id);
        }

        $session->update([
            'closed_by' => $closedBy,
            'counted_closing' => $countedClosing,
            'status' => CashRegisterSessionStatus::Closed,
            'closed_at' => now(),
        ]);

        return $session;
    }
}
