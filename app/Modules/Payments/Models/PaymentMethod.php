<?php

namespace App\Modules\Payments\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = ['name', 'status'];
}
