<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CredentialSkillMap extends Model
{
    protected $fillable = ['tenant_id','credential_type','credential_id','skill_definition_id','skill_code','skill_name','required_score','metadata'];
    protected $casts = ['metadata'=>'array'];
}
