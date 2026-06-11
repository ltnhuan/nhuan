<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiSyncJobItem extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'sync_job_id', 'entity_type', 'entity_id', 'external_id', 'status', 'error_message', 'payload'];

    protected $casts = ['payload' => 'array'];
}
