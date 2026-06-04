<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentApproval extends Model
{
    use HasFactory;

    protected $table = 'content_approvals';

    protected $fillable = ['tenant_id', 'entity_type', 'entity_id', 'from_status', 'to_status', 'requested_by', 'reviewed_by', 'decision', 'note', 'decided_at'];

    protected $casts = [
        'decided_at' => 'datetime'
    ];
}
