<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataIntegrityCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'module',
        'check_key',
        'title',
        'description',
        'severity',
        'status',
        'failed_count',
        'last_run_at',
        'metadata',
    ];

    protected $casts = [
        'last_run_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function issues()
    {
        return $this->hasMany(DataIntegrityIssue::class, 'check_id');
    }
}
