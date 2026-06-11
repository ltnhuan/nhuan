<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalTool extends Model
{
    protected $fillable = ['tenant_id', 'name', 'category', 'provider', 'launch_type', 'launch_url', 'lti_registration_id', 'status', 'capabilities', 'settings'];
    protected $casts = ['capabilities' => 'array', 'settings' => 'array'];
}
