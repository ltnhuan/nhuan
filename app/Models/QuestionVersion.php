<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionVersion extends Model
{
    public $timestamps = false;

    protected $fillable = ['tenant_id', 'question_id', 'version', 'snapshot', 'change_note', 'created_by', 'created_at'];

    protected $casts = ['snapshot' => 'array', 'created_at' => 'datetime'];
}
