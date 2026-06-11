<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LtiRegistration extends Model
{
    protected $fillable = ['tenant_id', 'name', 'issuer', 'client_id', 'deployment_id', 'login_url', 'launch_url', 'jwks_url', 'tool_type', 'status', 'scopes', 'settings'];
    protected $casts = ['scopes' => 'array', 'settings' => 'array'];

    public function launches()
    {
        return $this->hasMany(LtiLaunch::class, 'registration_id');
    }
}
