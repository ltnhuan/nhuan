<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LtiLaunch extends Model
{
    protected $fillable = ['tenant_id', 'registration_id', 'user_id', 'resource_link_id', 'target_link_uri', 'roles', 'claims', 'status', 'launched_at'];
    protected $casts = ['roles' => 'array', 'claims' => 'array', 'launched_at' => 'datetime'];
}
