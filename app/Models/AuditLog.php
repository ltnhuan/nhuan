<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    protected $fillable = ['tenant_id', 'actor_id', 'action', 'module', 'entity_type', 'entity_id', 'before', 'after', 'ip_address', 'user_agent'];

    protected $casts = [
        'before' => 'array',
        'after' => 'array'
    ];
}
