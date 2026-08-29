<?php

namespace App\Modules\Accounting\Actions;

use App\Modules\Accounting\Enums\AccountStatus;
use App\Modules\Accounting\Exceptions\DuplicateAccountCodeException;
use App\Modules\Accounting\Models\Account;

class CreateAccount
{
    public function handle(string $code, string $name, string $type, ?Account $parent = null): Account
    {
        if (Account::where('code', $code)->exists()) {
            throw DuplicateAccountCodeException::forCode($code);
        }

        return Account::create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'parent_id' => $parent?->id,
            'status' => AccountStatus::Active,
        ]);
    }
}
