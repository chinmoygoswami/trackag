<?php

namespace App\Models;

use App\Traits\TenantConnectionTrait;
use Illuminate\Database\Eloquent\Model;

class PaymentCollectionPlan extends Model
{
    use TenantConnectionTrait;
}
