<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningPathRule extends Model
{
    use HasFactory;

    protected $table = 'learning_path_rules';

    protected $fillable = ['tenant_id', 'course_id', 'target_type', 'target_id', 'rule_type', 'title', 'description', 'is_active', 'config', 'created_by'];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array'
    ];
}
