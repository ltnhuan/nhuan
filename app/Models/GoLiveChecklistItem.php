<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * GoLive Checklist Item Model
 * 
 * Tracks all tasks and verification steps required for production deployment
 */
class GoLiveChecklistItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'category',
        'item_code',
        'title',
        'description',
        'verification_method',
        'estimated_hours',
        'assigned_to',
        'status',
        'completed_at',
        'completed_by',
        'notes',
        'priority',
        'dependencies',
        'evidence_url',
        'risk_level',
        'sequence',
    ];

    protected $casts = [
        'dependencies' => 'array',
        'completed_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignee()
    {
        return $this->belongsTo(LmsUser::class, 'assigned_to');
    }

    public function completedBy()
    {
        return $this->belongsTo(LmsUser::class, 'completed_by');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeIncomplete($query)
    {
        return $query->where('status', '!=', 'completed');
    }

    public function scopeBlocking($query)
    {
        return $query->where('risk_level', 'critical')
            ->where('status', '!=', 'completed');
    }

    public function scopeOverdue($query)
    {
        return $query->incomplete()
            ->where('estimated_completion', '<', now());
    }

    public function mark_complete($user_id, $notes = null)
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $user_id,
            'notes' => $notes,
        ]);
    }

    public function mark_failed($notes)
    {
        $this->update([
            'status' => 'failed',
            'notes' => $notes,
        ]);
    }
}
