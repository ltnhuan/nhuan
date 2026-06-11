<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnrollmentEvent extends Model
{
    protected $fillable = ['tenant_id', 'enrollment_id', 'actor_id', 'event_type', 'from_status', 'to_status', 'metadata'];

    protected $casts = ['metadata' => 'array'];
}
