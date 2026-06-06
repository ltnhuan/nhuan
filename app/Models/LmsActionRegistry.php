<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LmsActionRegistry extends Model
{
    protected $table = 'lms_action_registry';

    protected $fillable = [
        'module',
        'action_key',
        'label',
        'route_name',
        'http_method',
        'permission_key',
        'confirm_required',
        'confirm_message',
        'success_message',
        'failure_message',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'confirm_required' => 'boolean',
        'is_active' => 'boolean',
    ];
}
