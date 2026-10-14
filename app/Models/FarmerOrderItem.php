<?php

namespace App\Models;

use App\Traits\TenantConnectionTrait;
use Illuminate\Database\Eloquent\Model;

class FarmerOrderItem extends Model
{
    use TenantConnectionTrait;

    protected $fillable = ['farmer_order_id', 'product_id', 'packing_id', 'shipper_size', 'price', 'total_price', 'gst', 'discount', 'grand_total', 'qty'];

    public function order() { return $this->belongsTo(FarmerOrder::class, 'farmer_order_id'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function packing() { return $this->belongsTo(ProductPacking::class, 'packing_id'); }
    public function dispatches() { return $this->hasMany(FarmerOrderDispatch::class, 'farmer_order_item_id'); }
}
