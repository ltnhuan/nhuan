<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateVerification extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id','certificate_issue_id','issue_code','verification_hash','valid','status','viewer_ip','viewer_user_agent','metadata','created_at'];
    protected $casts = ['valid'=>'boolean','metadata'=>'array','created_at'=>'datetime'];
}
