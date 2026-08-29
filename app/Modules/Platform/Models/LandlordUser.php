<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * The platform owner's own login — separate from the tenant `users`
 * table (which lives inside each tenant's own database) and from the
 * tenant Access/Roles system. Used only to protect landlord-domain
 * routes such as SaaS Billing; there is deliberately no roles/
 * permissions layer here, just an authenticated platform admin.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class LandlordUser extends Authenticatable
{
    use Notifiable;

    protected $connection = 'landlord';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
