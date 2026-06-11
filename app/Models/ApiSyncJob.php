<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiSyncJob extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'system_id', 'job_type', 'entity_type', 'status', 'total_count', 'success_count', 'failed_count', 'started_at', 'finished_at', 'error_report', 'created_by'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'error_report' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(ApiSyncJobItem::class, 'sync_job_id');
    }
}
