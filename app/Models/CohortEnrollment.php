<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CohortEnrollment extends Model
{
    protected $fillable = ['tenant_id', 'cohort_id', 'cohort_group_id', 'user_id', 'source', 'status', 'joined_at', 'left_at', 'metadata'];

    protected $casts = ['joined_at' => 'datetime', 'left_at' => 'datetime', 'metadata' => 'array'];
}
