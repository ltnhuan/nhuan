<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LmsUser extends Model
{
    use HasFactory;

    protected $table = 'lms_users';

    protected $fillable = [
        'tenant_id', 'sis_user_id', 'code', 'full_name', 'email', 'phone', 'avatar_url',
        'user_type', 'status', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function roleScopes()
    {
        return $this->hasMany(UserRoleScope::class, 'user_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role_scope', 'user_id', 'role_id')
            ->withPivot(['tenant_id', 'campus_id', 'academic_unit_id', 'course_id', 'class_id'])
            ->withTimestamps();
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
