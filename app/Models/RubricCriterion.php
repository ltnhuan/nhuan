<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RubricCriterion extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id','rubric_id','title','description','max_score','sort_order','metadata'];
    protected $casts = ['metadata'=>'array'];

    public function rubric() { return $this->belongsTo(Rubric::class); }
    public function levels() { return $this->hasMany(RubricLevel::class, 'criterion_id')->orderBy('sort_order'); }
}
