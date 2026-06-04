<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseVersion extends Model
{
    use HasFactory;

    protected $table = 'course_versions';

    protected $fillable = ['tenant_id', 'course_id', 'version', 'title_snapshot', 'structure_snapshot', 'change_note', 'created_by', 'created_at'];

    protected $casts = [
        'structure_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function creator()
    {
        return $this->belongsTo(LmsUser::class, 'created_by');
    }
}
