<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityGroup extends Model
{
    protected $fillable = ['tenant_id','group_type','course_id','name','description','visibility','status','owner_id','member_count','settings'];
    protected $casts = ['settings'=>'array'];

    public function members() { return $this->hasMany(CommunityGroupMember::class, 'group_id'); }
}
