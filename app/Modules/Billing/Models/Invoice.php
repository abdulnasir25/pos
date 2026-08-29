<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * paid_at/status=paid is set only by Actions/RecordInvoicePayment — a
 * manual, administrative action. No payment gateway is integrated
 * anywhere in this module.
 */
class Invoice extends Model
{
    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'amount',
        'status',
        'period_start',
        'period_end',
        'due_date',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
