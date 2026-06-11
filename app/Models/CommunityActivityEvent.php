<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityActivityEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id','user_id','event_type','forum_id','thread_id','metadata','created_at'];
    protected $casts = ['metadata'=>'array','created_at'=>'datetime'];
}
