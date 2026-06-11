<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiDataMapping extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'source_system_id', 'target_system_id', 'entity_type', 'source_field', 'target_field', 'transform_rule', 'is_required', 'default_value', 'status'];

    protected $casts = [
        'transform_rule' => 'array',
        'is_required' => 'boolean',
    ];
}
