<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiAuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'actor_id', 'action', 'module', 'entity_type', 'entity_id', 'before', 'after', 'ip_address', 'created_at'];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];
}
