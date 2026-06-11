<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReputationBadge extends Model
{
    protected $fillable = ['tenant_id','code','name','description','required_points','criteria'];
    protected $casts = ['criteria'=>'array'];
}
