<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityNotification extends Model
{
    protected $fillable = ['tenant_id','user_id','notification_type','title','body','action_url','payload','read_at'];
    protected $casts = ['payload'=>'array','read_at'=>'datetime'];
}
