<?php
namespace App\Services;
use App\Models\Exam;
use App\Models\ExamEnrollment;
class ExamEnrollmentService
{
    public function assignExamToUser(Exam $exam,int $userId,?int $assignedBy=null,array $data=[]): ExamEnrollment { return ExamEnrollment::query()->updateOrCreate(['tenant_id'=>$exam->tenant_id,'exam_id'=>$exam->id,'user_id'=>$userId], $data + ['course_id'=>$exam->course_id,'status'=>'assigned','assigned_by'=>$assignedBy]); }
    public function assignExamToClass(Exam $exam,int $classId,array $userIds,int $assignedBy): int { foreach($userIds as $id) $this->assignExamToUser($exam,$id,$assignedBy,['class_id'=>$classId]); return count($userIds); }
    public function checkUserEligibility(Exam $exam,int $userId): bool { if($exam->status!=='published') return false; if($exam->open_at && now()->lt($exam->open_at)) return false; if($exam->close_at && now()->gt($exam->close_at)) return false; return ExamEnrollment::query()->where(['exam_id'=>$exam->id,'user_id'=>$userId])->whereIn('status',['assigned','available'])->exists(); }
    public function getAvailableExamsForUser(int $tenantId,int $userId) { return Exam::query()->where('tenant_id',$tenantId)->where('status','published')->whereHas('attempts',fn($q)=>$q->where('user_id',$userId))->orWhereIn('id', ExamEnrollment::query()->where('tenant_id',$tenantId)->where('user_id',$userId)->pluck('exam_id'))->paginate(25); }
}
