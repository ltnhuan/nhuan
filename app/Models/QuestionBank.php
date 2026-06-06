<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionBank extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'academic_unit_id', 'course_id', 'code', 'name', 'description', 'visibility', 'status', 'owner_id', 'settings'];

    protected $casts = ['settings' => 'array'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function academicUnit()
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    public function owner()
    {
        return $this->belongsTo(LmsUser::class, 'owner_id');
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function categories()
    {
        return $this->hasMany(QuestionCategory::class);
    }
}
