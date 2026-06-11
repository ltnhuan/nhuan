<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiErrorRule extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'error_code', 'pattern', 'severity', 'auto_retry', 'max_retry', 'notify_roles', 'recommended_action'];

    protected $casts = [
        'auto_retry' => 'boolean',
        'notify_roles' => 'array',
    ];
}
