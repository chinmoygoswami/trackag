<?php

namespace App\Models;

use App\Traits\TenantConnectionTrait;
use Illuminate\Database\Eloquent\Model;

class FarmerOrderDispatch extends Model
{
    use TenantConnectionTrait;

    protected $fillable = ['farmer_order_id', 'farmer_order_item_id', 'dispatch_qty', 'dispatch_type'];

    public function order() { return $this->belongsTo(FarmerOrder::class, 'farmer_order_id'); }
    public function orderItem() { return $this->belongsTo(FarmerOrderItem::class, 'farmer_order_item_id'); }
    public function detail() { return $this->hasOne(FarmerOrderDispatchDetail::class); }
}
