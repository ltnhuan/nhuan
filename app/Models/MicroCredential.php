<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MicroCredential extends Model
{
    protected $fillable = ['tenant_id','code','title','credential_type','industry','level','outcome_statement','criteria','metadata','status'];
    protected $casts = ['criteria'=>'array','metadata'=>'array'];
}
