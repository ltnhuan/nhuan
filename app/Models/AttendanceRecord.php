<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class AttendanceRecord extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','attendance_session_id','live_session_id','user_id','status','checkin_at','checkout_at','attended_minutes','source','note','verified_by','metadata'];
    protected $casts=['checkin_at'=>'datetime','checkout_at'=>'datetime','metadata'=>'array'];
    public function session(){return $this->belongsTo(AttendanceSession::class,'attendance_session_id');}
    public function user(){return $this->belongsTo(LmsUser::class,'user_id');}
}
