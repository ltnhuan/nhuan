<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscussionThread extends Model
{
    protected $fillable = ['tenant_id','forum_id','thread_type','title','excerpt','status','is_pinned','is_locked','created_by','last_post_by','reply_count','view_count','like_count','upvote_count','correct_post_id','last_activity_at'];
    protected $casts = ['is_pinned'=>'boolean','is_locked'=>'boolean','last_activity_at'=>'datetime'];

    public function forum() { return $this->belongsTo(CommunityForum::class, 'forum_id'); }
    public function posts() { return $this->hasMany(DiscussionPost::class, 'thread_id'); }
    public function creator() { return $this->belongsTo(LmsUser::class, 'created_by'); }
    public function correctPost() { return $this->belongsTo(DiscussionPost::class, 'correct_post_id'); }
}
