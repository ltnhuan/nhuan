<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScormEvent extends Model
{
    protected $fillable = ['tenant_id', 'attempt_id', 'package_id', 'event_type', 'progress', 'score', 'completion_status', 'payload', 'occurred_at'];
    protected $casts = ['payload' => 'array', 'occurred_at' => 'datetime'];
}
