<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CohortRule extends Model
{
    protected $fillable = ['tenant_id', 'cohort_id', 'name', 'rule_type', 'conditions', 'action', 'priority', 'enabled', 'last_evaluated_at'];

    protected $casts = ['conditions' => 'array', 'enabled' => 'boolean', 'last_evaluated_at' => 'datetime'];

    public function cohort() { return $this->belongsTo(Cohort::class); }
}
