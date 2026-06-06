<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentSubmissionFile extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id','submission_id','file_name','file_path','mime_type','file_size','checksum','uploaded_by','created_at'];
    protected $casts = ['created_at'=>'datetime'];

    public function submission() { return $this->belongsTo(AssignmentSubmission::class, 'submission_id'); }
}
