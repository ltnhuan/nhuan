<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BadgeIssue extends Model
{
    protected $fillable = ['tenant_id','badge_id','user_id','course_id','issue_code','status','issued_at','verification_hash','verification_url','evidence','portfolio_item_id'];
    protected $casts = ['issued_at'=>'datetime','evidence'=>'array'];

    public function badge(){return $this->belongsTo(Badge::class);}
    public function learner(){return $this->belongsTo(LmsUser::class, 'user_id');}
}
