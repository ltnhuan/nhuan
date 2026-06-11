<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'legal_name', 'domain', 'status', 'logo_url', 'primary_color',
        'secondary_color', 'locale', 'timezone', 'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function campuses()
    {
        return $this->hasMany(Campus::class);
    }

    public function organizations()
    {
        return $this->hasMany(Organization::class);
    }

    public function academicUnits()
    {
        return $this->hasMany(AcademicUnit::class);
    }

    public function users()
    {
        return $this->hasMany(LmsUser::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }
}
