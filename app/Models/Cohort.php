<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cohort extends Model
{
    protected $fillable = ['tenant_id', 'code', 'name', 'type', 'status', 'academic_unit_id', 'created_by', 'start_date', 'end_date', 'metadata'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'metadata' => 'array'];

    public function groups() { return $this->hasMany(CohortGroup::class); }
    public function rules() { return $this->hasMany(CohortRule::class); }
    public function enrollments() { return $this->hasMany(CohortEnrollment::class); }
}
