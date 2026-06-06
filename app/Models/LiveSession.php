<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class LiveSession extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','course_id','class_id','component_id','title','description','provider','meeting_url','external_meeting_id','start_at','end_at','status','attendance_required','min_attendance_minutes','settings','created_by'];
    protected $casts=['start_at'=>'datetime','end_at'=>'datetime','attendance_required'=>'boolean','settings'=>'array'];
    public function attendanceSessions(){return $this->hasMany(AttendanceSession::class);}
}
