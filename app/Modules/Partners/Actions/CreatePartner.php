<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Partners\Enums\PartnerStatus;
use App\Modules\Partners\Models\Partner;

class CreatePartner
{
    public function handle(string $name, string $joinedAt, ?string $phone = null): Partner
    {
        return Partner::create([
            'name' => $name,
            'phone' => $phone,
            'joined_at' => $joinedAt,
            'status' => PartnerStatus::Active,
        ]);
    }
}
