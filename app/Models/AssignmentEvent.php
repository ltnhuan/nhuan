<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id','assignment_id','submission_id','user_id','event_type','metadata','created_at'];
    protected $casts = ['metadata'=>'array','created_at'=>'datetime'];
}
