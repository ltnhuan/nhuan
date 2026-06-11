<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CredentialEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id','credential_type','credential_id','user_id','event_type','source','payload','created_at'];
    protected $casts = ['payload'=>'array','created_at'=>'datetime'];
}
