<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiDataContract extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'system_id', 'entity_type', 'version', 'schema', 'status', 'effective_from', 'created_by'];

    protected $casts = [
        'schema' => 'array',
        'effective_from' => 'datetime',
    ];
}
