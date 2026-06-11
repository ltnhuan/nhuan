<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityGroupMember extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id','group_id','user_id','role','status','joined_at'];
    protected $casts = ['joined_at'=>'datetime'];
}
