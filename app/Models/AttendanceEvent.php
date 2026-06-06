<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AttendanceEvent extends Model
{
    public $timestamps=false;
    protected $fillable=['tenant_id','attendance_session_id','live_session_id','user_id','event_type','metadata','created_at'];
    protected $casts=['metadata'=>'array','created_at'=>'datetime'];
}
