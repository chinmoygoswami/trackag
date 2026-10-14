<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantConnectionTrait;


class ProductPacking extends Model
{
    use TenantConnectionTrait;

    protected $fillable = [
        'product_id',
        'packing_value',
        'packing_size',
        'shipper_type',
        'shipper_size',
        'unit_in_shipper',
        'status'
    ];

    protected $casts = [
        'packing_value' => 'float',
        'shipper_size' => 'float',
        'unit_in_shipper' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function packingStates()
    {
        return $this->hasMany(PackingState::class, 'packing_id', 'id');
    }

    public function prices()
    {
        return $this->hasMany(ProductPackingPrice::class, 'packing_id');
    }

    public function farmerPrices()
    {
        return $this->hasMany(FarmerProductPackingPrice::class, 'packing_id');
    }

    public function stock()
    {
        return $this->hasOne(Stock::class, 'packing_id');
    }

}
