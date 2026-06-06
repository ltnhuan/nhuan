<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SyncConflict extends Model
{
    protected $fillable=['tenant_id','system_id','entity_type','local_id','external_id','conflict_type','local_snapshot','external_snapshot','status','resolved_by','resolved_at'];
    protected $casts=['local_snapshot'=>'array','external_snapshot'=>'array','resolved_at'=>'datetime'];
}
