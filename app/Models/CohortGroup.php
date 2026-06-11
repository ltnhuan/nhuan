<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CohortGroup extends Model
{
    protected $fillable = ['tenant_id', 'cohort_id', 'code', 'name', 'group_type', 'capacity', 'status', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function cohort() { return $this->belongsTo(Cohort::class); }
}
