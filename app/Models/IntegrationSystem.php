<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class IntegrationSystem extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','code','name','system_type','base_url','auth_type','credentials_encrypted','status','settings'];
    protected $casts=['settings'=>'array'];
    public function mappings(){return $this->hasMany(IntegrationMapping::class,'system_id');}
    public function events(){return $this->hasMany(IntegrationEvent::class,'system_id');}
}
