<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantConnectionTrait;

class Order extends Model
{
    use TenantConnectionTrait;
    protected $fillable = [
        'order_no',
        'user_id',
        'party_id',
        'order_type',
        'depo_id',
        'delivery_place',
        'preferred_transport',
        'remark',
        'status',
        'remark2',
        'lr_number',
        'transport_name',
        'destination',
        'dispatch_date',
        'dispatch_image'
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function images()
    {
        return $this->hasMany(OrderImage::class);
    }

    public function dispatches()
    {
        return $this->hasMany(OrderDispatch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'party_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function depo(){
        return $this->belongsTo(Depo::class);
    
    }


}
