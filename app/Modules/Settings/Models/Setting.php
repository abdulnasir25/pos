<?php

namespace App\Modules\Settings\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

/**
 * Always exactly zero or one row — see Support\CurrentSettings, the
 * only supported way to read it (creates the row on first access
 * rather than requiring a seeded default).
 */
class Setting extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'shop_name',
        'address',
        'phone',
        'currency_symbol',
        'receipt_footer',
    ];
}
