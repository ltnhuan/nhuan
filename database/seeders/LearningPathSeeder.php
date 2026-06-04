<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\LearningCompletion;
use App\Models\LearningPathRule;
use App\Models\LearningProgressEvent;
use App\Models\LmsUser;
use App\Models\Tenant;
use App\Models\UserCourseProgress;
use Illuminate\Database\Seeder;

class LearningPathSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $courses = Course::query()->where('tenant_id', $tenant->id)->with('sections.components')->take(15)->get();
        foreach ($courses as $i => $course) {
            $mode = $i < 5 ? 'sequential' : ($i < 10 ? 'prerequisite' : ($i < 13 ? 'mastery' : 'adaptive'));
            $course->forceFill(['settings' => array_merge($course->settings ?? [], ['learning_mode' => $mode])])->save();
            $components = $course->sections->flatMap->components->values();
            for ($c = 1; $c < $components->count(); $c++) {
                LearningPathRule::query()->updateOrCreate(['tenant_id' => $tenant->id, 'course_id' => $course->id, 'target_type' => 'course_component', 'target_id' => $components[$c]->id], ['rule_type' => $mode === 'mastery' ? 'mastery' : 'sequential', 'title' => 'Mở khóa '.$components[$c]->title, 'description' => 'Hoàn thành bài trước', 'is_active' => true, 'config' => ['requires' => [['type' => $mode === 'mastery' ? 'quiz_score_min' : 'component_completed', 'component_id' => $components[$c - 1]->id, 'min_score' => 70]], 'unlock_behavior' => 'all_required', 'message_locked' => 'Bạn cần hoàn thành bài trước để mở bài này.'], 'created_by' => 1]);
            }
        }
        $students = LmsUser::query()->where('tenant_id', $tenant->id)->where('user_type', 'student')->take(500)->get();
        $firstCourse = $courses->first();
        $firstComponents = $firstCourse?->sections->flatMap->components->take(3) ?? collect();
        foreach ($students as $index => $student) {
            $completed = $index % 4;
            foreach ($firstComponents->take($completed) as $component) {
                LearningCompletion::query()->updateOrCreate(['tenant_id' => $tenant->id, 'user_id' => $student->id, 'course_id' => $component->course_id, 'component_id' => $component->id], ['section_id' => $component->section_id, 'completion_type' => 'component', 'status' => 'completed', 'progress_percent' => 100, 'source' => 'system', 'completed_at' => now(), 'metadata' => ['seed' => true]]);
                LearningProgressEvent::query()->create(['tenant_id' => $tenant->id, 'user_id' => $student->id, 'course_id' => $component->course_id, 'section_id' => $component->section_id, 'component_id' => $component->id, 'event_type' => 'component_completed', 'event_value' => 100, 'metadata' => ['seed' => true]]);
            }
            if ($firstCourse) {
                UserCourseProgress::query()->updateOrCreate(['tenant_id' => $tenant->id, 'user_id' => $student->id, 'course_id' => $firstCourse->id], ['status' => $completed > 0 ? 'in_progress' : 'not_started', 'progress_percent' => $completed * 10, 'completed_components_count' => $completed, 'total_components_count' => 27, 'completed_required_count' => $completed, 'total_required_count' => 27, 'risk_level' => $completed === 0 ? 'medium' : 'low', 'metadata' => ['seed' => true]]);
            }
        }
    }
}
