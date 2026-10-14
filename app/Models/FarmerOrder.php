<?php

namespace App\Models;

use App\Traits\TenantConnectionTrait;
use Illuminate\Database\Eloquent\Model;

class FarmerOrder extends Model
{
    use TenantConnectionTrait;

    protected $fillable = [
        'order_no', 'user_id', 'farmer_id', 'order_type', 'depo_id',
        'delivery_place', 'preferred_transport', 'remark', 'status', 'remark2',
        'lr_number', 'transport_name', 'destination', 'dispatch_date', 'dispatch_image',
    ];

    protected $casts = ['dispatch_date' => 'datetime'];

    public function farmer() { return $this->belongsTo(Farmer::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function depo() { return $this->belongsTo(Depo::class); }
    public function items() { return $this->hasMany(FarmerOrderItem::class); }
    public function dispatches() { return $this->hasMany(FarmerOrderDispatch::class); }
}
