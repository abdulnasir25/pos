<?php

namespace App\Modules\Products\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = ['name', 'abbreviation'];
}
