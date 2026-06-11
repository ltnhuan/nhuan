<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WikiPage extends Model
{
    protected $fillable = ['tenant_id','wiki_type','course_id','group_id','slug','title','body_html','version','status','created_by','updated_by'];
}
