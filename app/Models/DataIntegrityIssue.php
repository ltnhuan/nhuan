<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataIntegrityIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'check_id',
        'module',
        'entity_type',
        'entity_id',
        'issue_key',
        'message',
        'severity',
        'auto_fix_available',
        'status',
        'fixed_by',
        'fixed_at',
        'metadata',
    ];

    protected $casts = [
        'auto_fix_available' => 'boolean',
        'fixed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function check()
    {
        return $this->belongsTo(DataIntegrityCheck::class, 'check_id');
    }
}
