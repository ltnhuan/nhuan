<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityType extends Model
{
    use HasFactory;

    protected $table = 'activity_types';

    protected $fillable = ['key', 'name', 'description', 'icon', 'enabled', 'config_schema', 'grading_supported', 'completion_supported'];

    protected $casts = [
        'enabled' => 'boolean',
        'config_schema' => 'array',
        'grading_supported' => 'boolean',
        'completion_supported' => 'boolean'
    ];
}
