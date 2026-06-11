<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $fillable = ['tenant_id','certificate_template_id','code','title','description','credential_type','issuer_name','rules','metadata','status'];
    protected $casts = ['rules'=>'array','metadata'=>'array'];

    public function template(){return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');}
    public function issues(){return $this->hasMany(CertificateIssue::class);}
}
