<?php

namespace App\Models;

use App\Traits\TenantConnectionTrait;
use Illuminate\Database\Eloquent\Model;

class TallyPartywisePaymentCredit extends Model
{
    use TenantConnectionTrait;

    protected $fillable = [
        'sr_no',
        'party_name',
        'payment_date',
        'payment_mode',
        'credit_amount',
        'raw_payload',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'credit_amount' => 'decimal:2',
        'raw_payload' => 'array',
    ];
}
