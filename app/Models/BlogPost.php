<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    protected $fillable = ['tenant_id','blog_type','author_id','course_id','title','slug','body_html','status','published_at','like_count','comment_count'];
    protected $casts = ['published_at'=>'datetime'];
}
