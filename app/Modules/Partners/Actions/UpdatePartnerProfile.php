<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Partners\Models\Partner;

class UpdatePartnerProfile
{
    public function handle(Partner $partner, string $name, ?string $phone = null): Partner
    {
        $partner->update([
            'name' => $name,
            'phone' => $phone,
        ]);

        return $partner;
    }
}
