<?php

namespace App\Models;

use App\Traits\TenantConnectionTrait;
use Illuminate\Database\Eloquent\Model;

class TallyPartySync extends Model
{
    use TenantConnectionTrait;

    protected $fillable = [
        'group_name',
        'party_name',
        'phone_1',
        'phone_2',
        'contact_person_name',
        'state',
        'district',
        'gst_no',
        'party_create_date',
        'address',
        'email',
        'pan_no',
        'credit_days',
        'credit_limit',
        'raw_payload',
    ];

    protected $casts = [
        'party_create_date' => 'date',
        'raw_payload' => 'array',
    ];
}
