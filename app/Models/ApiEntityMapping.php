<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiEntityMapping extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'system_id', 'entity_type', 'local_id', 'external_id', 'external_code', 'status', 'metadata'];

    protected $casts = ['metadata' => 'array'];
}
