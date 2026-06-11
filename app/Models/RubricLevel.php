<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RubricLevel extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id','criterion_id','level_name','description','score','sort_order'];

    public function criterion() { return $this->belongsTo(RubricCriterion::class, 'criterion_id'); }
}
