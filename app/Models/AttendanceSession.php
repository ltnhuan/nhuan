<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class AttendanceSession extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','live_session_id','course_id','class_id','title','attendance_type','open_at','close_at','qr_token','otp_code','status','created_by','locked_by','locked_at','settings'];
    protected $casts=['open_at'=>'datetime','close_at'=>'datetime','locked_at'=>'datetime','settings'=>'array'];
    public function liveSession(){return $this->belongsTo(LiveSession::class);}
    public function records(){return $this->hasMany(AttendanceRecord::class);}
}
