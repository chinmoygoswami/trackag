<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\TenantConnectionTrait;


class Farmer extends Model
{
    use HasFactory;
    use TenantConnectionTrait;


    protected $fillable = [
        'user_id',
        'mobile_no',
        'mobile_no_2',
        'farmer_name',
        'village',
        'state_id',
        'district_id',
        'taluka_id',
        'crop_sowing_id',
        'land_acr',
        'irrigation_type',
        'land_acr_size',
        'latitude',
        'longitude'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function taluka()
    {
        return $this->belongsTo(Tehsil::class);
    }

    // public function cropSowing()
    // {
    //     return $this->belongsTo(CropSubCategory::class, 'crop_sowing_id');
    // }

    public function cropSowings()
    {
        return $this->hasMany(FarmerCropSowing::class, 'farmer_id');
    }

    public function latestFarmVisit()
    {
        return $this->hasOne(FarmVisit::class, 'farmer_id')->latestOfMany();
    }
}
