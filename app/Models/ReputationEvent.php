<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReputationEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id','user_id','points','event_type','source_id','source_type','metadata','created_at'];
    protected $casts = ['metadata'=>'array','created_at'=>'datetime'];
}
