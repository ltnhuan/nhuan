<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnrollmentImportJob extends Model
{
    protected $fillable = ['tenant_id', 'source', 'format', 'status', 'file_path', 'total_rows', 'success_rows', 'failed_rows', 'created_by', 'started_at', 'completed_at', 'payload', 'errors'];

    protected $casts = ['started_at' => 'datetime', 'completed_at' => 'datetime', 'payload' => 'array', 'errors' => 'array'];
}
