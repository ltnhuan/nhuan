<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscussionMention extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id','post_id','mentioned_user_id','mentioned_by','created_at'];
    protected $casts = ['created_at'=>'datetime'];
}
