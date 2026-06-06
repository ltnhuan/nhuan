<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModerationReport extends Model
{
    protected $fillable = ['tenant_id','reportable_type','reportable_id','reported_by','reason','note','status','resolved_by','resolved_at'];
    protected $casts = ['resolved_at'=>'datetime'];
}
