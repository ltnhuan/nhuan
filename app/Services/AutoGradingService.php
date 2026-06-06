<?php
namespace App\Services;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamResult;
class AutoGradingService
{
    public function gradeAnswer(ExamAnswer $answer): ExamAnswer
    {
        $snapshot = $answer->attemptQuestion->question_snapshot ?? [];
        $type = $snapshot['question_type'] ?? 'essay';
        return match($type) {
            'single_choice' => $this->gradeSingleChoice($answer),
            'multiple_choice' => $this->gradeMultipleChoice($answer),
            'true_false' => $this->gradeTrueFalse($answer),
            'fill_blank' => $this->gradeFillBlank($answer),
            'matching' => $this->gradeMatching($answer),
            'ordering' => $this->gradeOrdering($answer),
            default => $this->skipEssayForManualGrading($answer),
        };
    }
    public function gradeSingleChoice(ExamAnswer $a): ExamAnswer { $correct=collect($a->attemptQuestion->options_snapshot)->firstWhere('is_correct',true)['option_key']??null; return $this->set($a, ($a->answer_data['option_key']??null)===$correct); }
    public function gradeMultipleChoice(ExamAnswer $a): ExamAnswer { $correct=collect($a->attemptQuestion->options_snapshot)->where('is_correct',true)->pluck('option_key')->sort()->values()->all(); $given=collect($a->answer_data['option_keys']??[])->sort()->values()->all(); return $this->set($a,$correct===$given); }
    public function gradeTrueFalse(ExamAnswer $a): ExamAnswer { return $this->gradeSingleChoice($a); }
    public function gradeFillBlank(ExamAnswer $a): ExamAnswer { $answers=$a->attemptQuestion->question_snapshot['fill_blank_answers']??[]; $ok=collect($answers)->every(fn($x)=>strtolower($a->answer_data[$x['blank_key']]??'')===strtolower($x['accepted_answer'])); return $this->set($a,$ok); }
    public function gradeMatching(ExamAnswer $a): ExamAnswer { return $this->set($a, ($a->answer_data['pairs']??[])===($a->attemptQuestion->question_snapshot['matching_pairs']??[])); }
    public function gradeOrdering(ExamAnswer $a): ExamAnswer { $correct=collect($a->attemptQuestion->options_snapshot)->pluck('option_key')->values()->all(); return $this->set($a,($a->answer_data['order']??[])===$correct); }
    public function skipEssayForManualGrading(ExamAnswer $a): ExamAnswer { $a->forceFill(['is_correct'=>null,'score'=>null,'feedback'=>'Chờ chấm tay'])->save(); return $a; }
    public function calculateAttemptScore(ExamAttempt $attempt): ExamAttempt { $score=(float)$attempt->answers()->sum('score'); $exam=$attempt->exam; $pass=$exam->pass_score!==null ? ($score >= (float)$exam->pass_score ? 'passed':'failed') : 'not_evaluated'; $attempt->forceFill(['score'=>$score,'pass_status'=>$pass,'graded_at'=>now(),'status'=>'graded'])->save(); ExamResult::query()->updateOrCreate(['tenant_id'=>$attempt->tenant_id,'attempt_id'=>$attempt->id],['exam_id'=>$exam->id,'user_id'=>$attempt->user_id,'score'=>$score,'max_score'=>$attempt->max_score,'percent'=>$attempt->max_score>0?round($score/$attempt->max_score*100,2):0,'pass_status'=>$pass,'published'=>$exam->show_result_mode==='immediately','published_at'=>$exam->show_result_mode==='immediately'?now():null]); return $attempt; }
    private function set(ExamAnswer $a,bool $ok): ExamAnswer { $a->forceFill(['is_correct'=>$ok,'score'=>$ok ? $a->attemptQuestion->score : 0,'graded_at'=>now()])->save(); return $a; }
}
