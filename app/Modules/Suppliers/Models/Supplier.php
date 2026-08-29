<?php

namespace App\Modules\Suppliers\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = ['name', 'phone', 'balance', 'status'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:2'];
    }
}
