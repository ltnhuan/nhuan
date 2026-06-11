<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScormAttempt extends Model
{
    protected $fillable = ['tenant_id', 'package_id', 'user_id', 'standard', 'status', 'progress', 'score', 'completion_status', 'success_status', 'session_time_seconds', 'runtime_data', 'started_at', 'completed_at'];
    protected $casts = ['runtime_data' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];

    public function package()
    {
        return $this->belongsTo(ScormPackage::class, 'package_id');
    }

    public function events()
    {
        return $this->hasMany(ScormEvent::class, 'attempt_id');
    }
}
