<?php

namespace App\Modules\Inventory\Enums;

enum MovementReason: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case PurchaseReturn = 'purchase_return';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case Adjustment = 'adjustment';
    case Damage = 'damage';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';

    /**
     * Reasons that always increase stock and therefore never need the
     * atomic conditional-update guard — only the destination row's
     * quantity being non-negative before the move matters, and increases
     * can't violate that.
     */
    public function isAlwaysIncrease(): bool
    {
        return match ($this) {
            self::Opening, self::Purchase, self::SaleReturn, self::TransferIn => true,
            self::PurchaseReturn, self::Sale, self::Adjustment, self::Damage, self::TransferOut => false,
        };
    }
}
