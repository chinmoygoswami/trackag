<?php

namespace App\Models;

use App\Traits\TenantConnectionTrait;
use Illuminate\Database\Eloquent\Model;

class TallyOpeningClosing extends Model
{
    use TenantConnectionTrait;

    protected $fillable = [
        'master_id',
        'date',
        'party_name',
        'opening_balance_amt',
        'credit_amt',
        'debit_amt',
        'closing_balance_amt',
        'raw_payload',
    ];

    protected $casts = [
        'date' => 'date',
        'opening_balance_amt' => 'decimal:2',
        'credit_amt' => 'decimal:2',
        'debit_amt' => 'decimal:2',
        'closing_balance_amt' => 'decimal:2',
        'raw_payload' => 'array',
    ];
}
