<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRoleScope extends Model
{
    use HasFactory;

    protected $table = 'user_role_scope';

    protected $fillable = [
        'user_id',
        'role_id',
        'tenant_id',
        'campus_id',
        'academic_unit_id',
        'course_id',
        'class_id',
    ];

    public function user()
    {
        return $this->belongsTo(LmsUser::class, 'user_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function academicUnit()
    {
        return $this->belongsTo(AcademicUnit::class);
    }
}
