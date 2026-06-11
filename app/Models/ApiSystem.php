<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiSystem extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'code', 'name', 'type', 'base_url', 'environment', 'auth_type', 'status', 'owner_team', 'description', 'settings'];

    protected $casts = ['settings' => 'array'];

    public function endpoints()
    {
        return $this->hasMany(ApiEndpoint::class, 'system_id');
    }

    public function credentials()
    {
        return $this->hasMany(ApiCredential::class, 'system_id');
    }
}
