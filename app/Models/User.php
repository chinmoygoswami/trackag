<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\TenantConnectionTrait;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens,TenantConnectionTrait;

    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($query) {
            if (tenancy()->tenant) {
                $query->getModel()->setConnection('tenant');
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'role',
        'email',
        'password',
        'mobile',
        'image',
        'user_type',
        'user_code',
        'headquarter',
        'date_of_birth',
        'joining_date',
        'emergency_contact_no',
        'gender',
        'marital_status',
        'designation_id',
        'role_rights',
        'reporting_to',
        'is_self_sale',
        'is_multi_day_start_end_allowed',
        'is_allow_tracking',
        'address',
        'state_id',
        'district_id',
        'city_id',
        'tehsil_id',
        'latitude',
        'longitude',
        'pincode',
        'depo',
        'postal_address',
        'status',
        'company_id',
        'user_level',
        'company_id',
        'user_level',
        'company_mobile',
        'village',
        'depo_id',
        'is_web_login_access',
        'account_no',
        'branch_name',
        'ifsc_code',
        'pan_card_no',
        'aadhar_no',
        'driving_lic_no',
        'driving_expiry',
        'passport_no',
        'passport_expiry',
        'cancel_cheque_photos',
        'slab_designation_id',
        'fcm_token'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'last_seen' => 'datetime',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'is_self_sale' => 'boolean',
            'is_multi_day_start_end_allowed' => 'boolean',
            'is_allow_tracking' => 'boolean',
        ];
    }

    // Existing relationships
    public function state() { return $this->belongsTo(State::class); }
    public function district() { return $this->belongsTo(District::class); }
    public function city() { return $this->belongsTo(City::class); }
    public function tehsil() { return $this->belongsTo(Tehsil::class); }
    public function pincode() { return $this->belongsTo(Pincode::class);}

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function isMasterAdmin()
    {
        return $this->user_level === 'master_admin';
    }

    public function getAllPermissionsList()
    {
        return $this->getAllPermissions()->pluck('name');
    }

    public function reportingManager()
    {
        return $this->belongsTo(User::class, 'reporting_to');
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function sessions()
    {
        return $this->hasMany(UserSession::class);
    }

    public function activeSessions()
    {
        return $this->hasMany(\App\Models\UserSession::class)->whereNull('logout_at');
    }

    public function depos()
    {
        return $this->belongsTo(Depo::class, 'depo_id', 'id');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function partyVisits()
    {
        return $this->hasMany(PartyVisit::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function partyPayments()
    {
        return $this->hasMany(PartyPayment::class);
    }

    public function farmVisits()
    {
        return $this->hasMany(FarmVisit::class);
    }

}
