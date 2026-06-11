<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateTemplate extends Model
{
    protected $fillable = ['tenant_id','code','name','type','language','status','canvas_schema','dynamic_fields','signature_image_path','translations','settings'];
    protected $casts = ['canvas_schema'=>'array','dynamic_fields'=>'array','translations'=>'array','settings'=>'array'];

    public function certificates(){return $this->hasMany(Certificate::class);}
}
