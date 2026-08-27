<?php

namespace App\Modules\Warehouses\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = ['name', 'status'];
}
