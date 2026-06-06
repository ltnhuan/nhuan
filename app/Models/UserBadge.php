<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBadge extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id','user_id','badge_id','awarded_at'];
    protected $casts = ['awarded_at'=>'datetime'];
}
