<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardMetricSnapshot extends Model
{
    protected $fillable = [
        'tenant_id',
        'snapshot_date',
        'academic_year_id',
        'semester_id',
        'campus_id',
        'faculty_id',
        'program_id',
        'class_id',
        'course_id',
        'user_id',
        'metric_key',
        'metric_value',
        'metric_unit',
        'dimension',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'metric_value' => 'float',
        'dimension' => 'array',
    ];
}
