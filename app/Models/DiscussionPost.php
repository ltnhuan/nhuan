<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscussionPost extends Model
{
    protected $fillable = ['tenant_id','thread_id','parent_id','quoted_post_id','user_id','post_type','body_html','body_text','status','is_pinned','is_correct_answer','like_count','upvote_count','metadata'];
    protected $casts = ['is_pinned'=>'boolean','is_correct_answer'=>'boolean','metadata'=>'array'];

    public function thread() { return $this->belongsTo(DiscussionThread::class, 'thread_id'); }
    public function author() { return $this->belongsTo(LmsUser::class, 'user_id'); }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function quote() { return $this->belongsTo(self::class, 'quoted_post_id'); }
    public function replies() { return $this->hasMany(self::class, 'parent_id'); }
    public function mentions() { return $this->hasMany(DiscussionMention::class, 'post_id'); }
    public function reactions() { return $this->hasMany(DiscussionReaction::class, 'post_id'); }
}
