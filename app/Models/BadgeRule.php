<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BadgeRule extends Model
{
    protected $fillable = ['tenant_id','badge_id','rule_type','source_type','source_id','conditions','auto_issue','status'];
    protected $casts = ['conditions'=>'array','auto_issue'=>'boolean'];

    public function badge(){return $this->belongsTo(Badge::class);}
}
