<?php

namespace App\Modules\CashRegister\Models;

use App\Modules\CashRegister\Enums\FinancialAccountStatus;
use App\Modules\CashRegister\Enums\FinancialAccountType;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'name',
        'account_type',
        'opening_balance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => FinancialAccountType::class,
            'opening_balance' => 'decimal:2',
            'status' => FinancialAccountStatus::class,
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashRegisterSession::class);
    }
}
