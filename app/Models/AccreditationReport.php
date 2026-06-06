<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AccreditationReport extends Model
{
    protected $fillable=['tenant_id','title','standard','report_type','format','status','filters','metrics','file_path','generated_by','generated_at'];
    protected $casts=['filters'=>'array','metrics'=>'array','generated_at'=>'datetime'];
}
