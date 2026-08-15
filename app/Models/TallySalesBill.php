<?php

namespace App\Models;

use App\Traits\TenantConnectionTrait;
use Illuminate\Database\Eloquent\Model;

class TallySalesBill extends Model
{
    use TenantConnectionTrait;

    protected $fillable = [
        'financial_year',
        'invoice_date',
        'party_name',
        'product_name_with_packing',
        'bill_type',
        'qty',
        'amount',
        'gst_amount',
        'grand_total',
        'raw_payload',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'qty' => 'decimal:3',
        'amount' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'raw_payload' => 'array',
    ];
}
