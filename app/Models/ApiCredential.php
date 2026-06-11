<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiCredential extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'system_id', 'name', 'credential_type', 'encrypted_value', 'expires_at', 'rotated_at', 'status'];

    protected $casts = [
        'expires_at' => 'datetime',
        'rotated_at' => 'datetime',
    ];
}
