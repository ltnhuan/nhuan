<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    protected $fillable = ['tenant_id','code','name','badge_type','description','image_url','criteria','metadata','status'];
    protected $casts = ['criteria'=>'array','metadata'=>'array'];

    public function rules(){return $this->hasMany(BadgeRule::class);}
    public function issues(){return $this->hasMany(BadgeIssue::class);}
}
