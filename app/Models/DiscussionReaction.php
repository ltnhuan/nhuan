<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscussionReaction extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id','post_id','thread_id','user_id','reaction_type','created_at'];
    protected $casts = ['created_at'=>'datetime'];
}
