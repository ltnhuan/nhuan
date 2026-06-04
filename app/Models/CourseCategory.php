<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseCategory extends Model
{
    use HasFactory;

    protected $table = 'course_categories';

    protected $fillable = ['tenant_id', 'parent_id', 'code', 'name', 'description', 'sort_order', 'status'];

    protected $casts = [
        
    ];
}
