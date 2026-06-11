<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManualCompletionApproval extends Model
{
    use HasFactory;

    protected $table = 'manual_completion_approvals';

    protected $fillable = ['tenant_id', 'user_id', 'course_id', 'component_id', 'section_id', 'requested_by', 'approved_by', 'status', 'note', 'decided_at'];

    protected $casts = [
        'decided_at' => 'datetime'
    ];
}
