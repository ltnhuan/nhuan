<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionMatchingPair extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'question_id', 'left_content', 'right_content', 'sort_order', 'metadata'];

    protected $casts = ['metadata' => 'array'];
}
