<?php

namespace App\Models;

use App\Traits\TenantConnectionTrait;
use Illuminate\Database\Eloquent\Model;

class FarmerOrderDispatchDetail extends Model
{
    use TenantConnectionTrait;

    protected $fillable = ['farmer_order_dispatch_id', 'lr_number', 'transport_name', 'vehicle_no', 'dispatch_image', 'dispatch_date'];

    protected $casts = ['dispatch_date' => 'datetime'];

    public function dispatch() { return $this->belongsTo(FarmerOrderDispatch::class, 'farmer_order_dispatch_id'); }
}
