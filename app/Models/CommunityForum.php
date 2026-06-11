<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityForum extends Model
{
    protected $fillable = ['tenant_id','forum_type','course_id','class_id','faculty_id','title','description','visibility','status','is_pinned','created_by','thread_count','post_count','last_activity_at','settings'];
    protected $casts = ['is_pinned'=>'boolean','last_activity_at'=>'datetime','settings'=>'array'];

    public function threads() { return $this->hasMany(DiscussionThread::class, 'forum_id'); }
    public function course() { return $this->belongsTo(Course::class); }
    public function creator() { return $this->belongsTo(LmsUser::class, 'created_by'); }
}
