<?php

namespace Database\Seeders;

use App\Models\AcademicUnit;
use App\Models\ClassSection;
use App\Models\Course;
use App\Models\LmsUser;
use App\Models\SurveyCampaign;
use App\Models\SurveyEvidenceFile;
use App\Models\SurveyForm;
use App\Models\SurveyImprovement;
use App\Services\SurveyService;
use Illuminate\Database\Seeder;

class SurveySeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        $service = app(SurveyService::class);
        $course = Course::query()->where('tenant_id', $tenantId)->first();
        $class = ClassSection::query()->where('tenant_id', $tenantId)->first();
        $unit = AcademicUnit::query()->where('tenant_id', $tenantId)->first();
        $teacher = LmsUser::query()->where('tenant_id', $tenantId)->where('user_type', 'teacher')->first();
        $students = LmsUser::query()->where('tenant_id', $tenantId)->where('user_type', 'student')->limit(12)->get();
        if (! $course || $students->isEmpty()) return;

        $teacherForm = SurveyForm::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'GV-360'],
            ['title' => 'Đánh giá giảng viên', 'survey_type' => 'teacher_evaluation', 'description' => 'Chuyên môn, phương pháp, tương tác, đúng giờ, hỗ trợ học viên.', 'status' => 'published', 'settings' => ['builder' => 'drag_drop'], 'created_by' => 1]
        );
        $service->syncQuestions($teacherForm, [
            ['code' => 'expertise', 'question_type' => 'rating', 'prompt' => 'Chuyên môn', 'required' => true],
            ['code' => 'methodology', 'question_type' => 'rating', 'prompt' => 'Phương pháp giảng dạy', 'required' => true],
            ['code' => 'interaction', 'question_type' => 'rating', 'prompt' => 'Tương tác với học viên', 'required' => true],
            ['code' => 'punctuality', 'question_type' => 'rating', 'prompt' => 'Đúng giờ', 'required' => true],
            ['code' => 'support', 'question_type' => 'rating', 'prompt' => 'Hỗ trợ học viên', 'required' => true],
            ['code' => 'teacher_nps', 'question_type' => 'nps', 'prompt' => 'Bạn có giới thiệu giảng viên này không?', 'required' => true],
            ['code' => 'comment', 'question_type' => 'text', 'prompt' => 'Góp ý cải tiến'],
        ]);

        $courseForm = SurveyForm::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'COURSE-QA'],
            ['title' => 'Đánh giá khóa học', 'survey_type' => 'course_evaluation', 'description' => 'Nội dung, bài giảng, khối lượng, đánh giá.', 'status' => 'published', 'settings' => ['builder' => 'drag_drop'], 'created_by' => 1]
        );
        $service->syncQuestions($courseForm, [
            ['code' => 'course_matrix', 'question_type' => 'matrix', 'prompt' => 'Đánh giá cấu phần khóa học', 'required' => true, 'rows' => ['Nội dung','Bài giảng','Khối lượng','Đánh giá'], 'columns' => [1,2,3,4,5]],
            ['code' => 'learning_value', 'question_type' => 'rating', 'prompt' => 'Giá trị học tập tổng thể', 'required' => true],
            ['code' => 'course_nps', 'question_type' => 'nps', 'prompt' => 'Recommendation Score cho khóa học', 'required' => true],
            ['code' => 'preferred_actions', 'question_type' => 'multi_select', 'prompt' => 'Bạn muốn cải tiến phần nào?', 'options' => ['Nội dung','Bài giảng','Bài tập','Thi đánh giá','Hỗ trợ']],
        ]);

        $campaign = SurveyCampaign::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'GV-CLASS-001'],
            ['survey_form_id' => $teacherForm->id, 'title' => 'Đánh giá giảng viên theo lớp', 'target_scope' => 'class', 'course_id' => $course->id, 'class_section_id' => $class?->id, 'teacher_id' => $teacher?->id, 'is_anonymous' => true, 'allow_identified' => true, 'status' => 'active', 'starts_at' => now()->subDays(10), 'ends_at' => now()->addDays(10), 'channels' => ['lms_notification'], 'settings' => ['reminder_days' => [3, 7]], 'created_by' => 1]
        );
        SurveyCampaign::query()->updateOrCreate(['tenant_id' => $tenantId, 'code' => 'COURSE-DEPT-001'], ['survey_form_id' => $courseForm->id, 'title' => 'Đánh giá khóa học theo khoa', 'target_scope' => 'academic_unit', 'course_id' => $course->id, 'academic_unit_id' => $unit?->id, 'is_anonymous' => true, 'allow_identified' => false, 'status' => 'active', 'starts_at' => now()->subDays(7), 'ends_at' => now()->addDays(14), 'channels' => ['email','lms_notification'], 'settings' => [], 'created_by' => 1]);
        SurveyCampaign::query()->updateOrCreate(['tenant_id' => $tenantId, 'code' => 'NPS-SCHOOL-001'], ['survey_form_id' => $courseForm->id, 'title' => 'Khảo sát NPS toàn trường', 'target_scope' => 'institution', 'is_anonymous' => true, 'allow_identified' => true, 'status' => 'active', 'starts_at' => now()->subDays(3), 'ends_at' => now()->addMonth(), 'channels' => ['email'], 'settings' => [], 'created_by' => 1]);

        if ($campaign->responses()->count() === 0) {
            foreach ($students as $index => $student) {
                $service->submitResponse($campaign, ['answers' => [
                    ['code' => 'expertise', 'value' => min(5, 3 + ($index % 3))],
                    ['code' => 'methodology', 'value' => min(5, 2 + ($index % 4))],
                    ['code' => 'interaction', 'value' => min(5, 3 + ($index % 3))],
                    ['code' => 'punctuality', 'value' => 4],
                    ['code' => 'support', 'value' => min(5, 2 + ($index % 4))],
                    ['code' => 'teacher_nps', 'value' => 6 + ($index % 5)],
                    ['code' => 'comment', 'value' => 'Cần thêm ví dụ thực tế và phản hồi nhanh hơn.'],
                ]], $student->id);
            }
        }

        $improvement = SurveyImprovement::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'survey_campaign_id' => $campaign->id, 'issue_title' => 'Tăng tương tác và phản hồi sau buổi học'],
            ['course_id' => $course->id, 'class_section_id' => $class?->id, 'teacher_id' => $teacher?->id, 'issue_description' => 'Điểm phương pháp và hỗ trợ thấp hơn ngưỡng 4.0.', 'improvement_action' => 'Bổ sung rubric phản hồi trong 48 giờ và hoạt động hỏi đáp cuối buổi.', 'result' => 'Theo dõi ở campaign kế tiếp.', 'status' => 'in_progress', 'priority' => 'high', 'owner_id' => $teacher?->id, 'due_date' => now()->addWeeks(2)->toDateString(), 'metrics' => ['baseline_support' => 3.4, 'target_support' => 4.2]]
        );

        SurveyEvidenceFile::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'survey_campaign_id' => $campaign->id, 'title' => 'Minh chứng khảo sát đánh giá giảng viên GV-CLASS-001'],
            ['survey_improvement_id' => $improvement->id, 'evidence_type' => 'survey_report', 'file_path' => 'evidence/surveys/GV-CLASS-001.html', 'mime_type' => 'text/html', 'checksum' => hash('sha256', 'GV-CLASS-001'), 'metadata' => ['standard' => 'AUN-QA', 'criteria' => ['stakeholder_feedback','quality_improvement']], 'created_by' => 1]
        );
    }
}
