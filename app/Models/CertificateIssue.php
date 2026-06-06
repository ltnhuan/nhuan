<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateIssue extends Model
{
    protected $fillable = ['tenant_id','certificate_id','certificate_template_id','user_id','course_id','learning_path_id','issue_code','learner_name','certificate_title','status','issued_at','expires_at','qr_payload','verification_hash','verification_url','language','field_values','sis_payload','portfolio_item_id','blockchain_status','blockchain_network','blockchain_tx_hash','revocation_reason','revoked_at'];
    protected $casts = ['issued_at'=>'datetime','expires_at'=>'datetime','revoked_at'=>'datetime','field_values'=>'array','sis_payload'=>'array'];

    public function certificate(){return $this->belongsTo(Certificate::class);}
    public function template(){return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');}
    public function learner(){return $this->belongsTo(LmsUser::class, 'user_id');}
    public function portfolioItem(){return $this->belongsTo(PortfolioItem::class, 'portfolio_item_id');}
}
