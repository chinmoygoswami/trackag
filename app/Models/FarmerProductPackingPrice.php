<?php

namespace App\Models;

use App\Traits\TenantConnectionTrait;
use Illuminate\Database\Eloquent\Model;

class FarmerProductPackingPrice extends Model
{
    use TenantConnectionTrait;

    protected $table = 'farmer_product_packing_prices';

    protected $fillable = [
        'product_id',
        'packing_id',
        'state_id',
        'cash_price',
        'credit_price',
    ];

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}
