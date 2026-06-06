<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rubric extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id','course_id','title','description','max_score','status','created_by'];

    public function criteria() { return $this->hasMany(RubricCriterion::class)->orderBy('sort_order'); }
}
