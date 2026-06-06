<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Question;
use App\Models\ExamBlueprint;
use Illuminate\Support\Facades\DB;

class ExamService
{
    public function createExam(array $data): Exam { $this->validateExamConfig($data); return Exam::query()->create($data + ['status'=>'draft']); }
    public function updateExam(Exam $exam, array $data): Exam { $this->validateExamConfig($data + $exam->toArray()); $exam->fill($data)->save(); return $exam; }
    public function publishExam(Exam $exam): Exam { $exam->forceFill(['status'=>'published'])->save(); return $exam; }
    public function closeExam(Exam $exam): Exam { $exam->forceFill(['status'=>'closed'])->save(); return $exam; }
    public function validateExamConfig(array $data): void { if (($data['pass_score'] ?? 0) > ($data['total_score'] ?? PHP_INT_MAX)) throw new \InvalidArgumentException('Điểm đạt không được lớn hơn tổng điểm.'); }

    public function buildExamFromBlueprint(Exam $exam): Exam
    {
        if (! $exam->blueprint_id) {
            throw new \InvalidArgumentException('Exam chưa gắn blueprint.');
        }

        $blueprint = ExamBlueprint::query()->findOrFail($exam->blueprint_id);
        DB::transaction(function () use ($exam, $blueprint) {
            ExamSection::query()->where('exam_id', $exam->id)->delete();
            ExamQuestion::query()->where('exam_id', $exam->id)->delete();
            $order = 1; $total = 0;
            $usedIds = [];
            foreach ($blueprint->config['sections'] ?? [] as $i => $sectionConfig) {
                $section = ExamSection::query()->create(['tenant_id'=>$exam->tenant_id,'exam_id'=>$exam->id,'title'=>$sectionConfig['name'],'sort_order'=>$i + 1,'question_count'=>$sectionConfig['question_count'],'score'=>($sectionConfig['question_count'] * ($sectionConfig['score_each'] ?? 1)),'config'=>$sectionConfig]);
                $query = Question::query()->where('tenant_id',$exam->tenant_id)->where('question_bank_id',$blueprint->question_bank_id)->whereIn('status',['approved','published']);
                if (!empty($sectionConfig['difficulty'])) $query->whereIn('difficulty',$sectionConfig['difficulty']);
                if (!empty($sectionConfig['bloom_level'])) $query->whereIn('bloom_level',$sectionConfig['bloom_level']);
                if ($usedIds) $query->whereNotIn('id', $usedIds);
                foreach ($query->inRandomOrder()->limit((int)$sectionConfig['question_count'])->get() as $question) {
                    ExamQuestion::query()->create(['tenant_id'=>$exam->tenant_id,'exam_id'=>$exam->id,'section_id'=>$section->id,'question_id'=>$question->id,'score'=>$sectionConfig['score_each'] ?? $question->default_score,'sort_order'=>$order++]);
                    $usedIds[] = $question->id;
                    $total += (float) ($sectionConfig['score_each'] ?? $question->default_score);
                }
            }
            $exam->forceFill(['total_score'=>$total])->save();
        });
        return $exam->fresh(['sections','questions']);
    }

    public function attachQuestions(Exam $exam, array $questionIds): int
    {
        foreach ($questionIds as $index => $questionId) {
            $question = Question::query()->findOrFail($questionId);
            ExamQuestion::query()->updateOrCreate(['exam_id'=>$exam->id,'question_id'=>$questionId], ['tenant_id'=>$exam->tenant_id,'score'=>$question->default_score,'sort_order'=>$index + 1]);
        }
        return count($questionIds);
    }
}
