<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicUnit extends Model
{
    use HasFactory;

    protected $table = 'academic_units';

    protected $fillable = ['tenant_id', 'organization_id', 'code', 'name', 'type', 'status'];

    protected $casts = [
        
    ];
}
