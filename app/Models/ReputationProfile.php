<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReputationProfile extends Model
{
    protected $fillable = ['tenant_id','user_id','points','rank','badge_count'];
}
