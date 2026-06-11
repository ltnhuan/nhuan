<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\LearningProgressEvent;
use App\Models\LmsUser;
use App\Models\OfflineProgress;
use App\Models\OfflineQuizAnswer;
use App\Models\OfflineQueue;
use Database\Seeders\CoreSeeder;
use Database\Seeders\CourseStudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileLearningFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(CourseStudioSeeder::class);
    }

    public function test_mobile_bootstrap_returns_courses_offline_contract_and_wallet(): void
    {
        $response = $this->withMobileHeaders()->getJson('/api/v1/mobile/bootstrap');

        $response->assertOk()
            ->assertJsonPath('platforms.0', 'ios')
            ->assertJsonPath('offline.tables.0', 'offline_queue')
            ->assertJsonStructure([
                'courses' => [['id', 'title', 'components']],
                'continue_learning',
                'notifications',
                'attendance',
                'ai_tutor',
                'portfolio',
                'credential_wallet',
            ]);
    }

    public function test_offline_lesson_progress_syncs_to_server_tables(): void
    {
        $course = Course::query()->firstOrFail();
        $component = CourseComponent::query()->where('course_id', $course->id)->firstOrFail();

        $response = $this->withMobileHeaders()->postJson('/api/v1/mobile/sync', [
            'device_id' => 'ios-device-1',
            'items' => [[
                'client_uuid' => 'progress-001',
                'operation' => 'progress.upsert',
                'payload' => [
                    'course_id' => $course->id,
                    'component_id' => $component->id,
                    'progress_percent' => 100,
                    'status' => 'completed',
                    'client_updated_at' => now()->toISOString(),
                    'metadata' => ['lesson_cached' => true],
                ],
            ]],
        ]);

        $response->assertOk()
            ->assertJsonPath('synced', 1)
            ->assertJsonPath('items.0.status', 'synced');

        $this->assertDatabaseHas('offline_queue', ['client_uuid' => 'progress-001', 'status' => 'synced']);
        $this->assertDatabaseHas('offline_progress', ['course_id' => $course->id, 'component_id' => $component->id, 'status' => 'completed']);
        $this->assertSame('offline_lesson_completed', LearningProgressEvent::query()->latest()->value('event_type'));
        $this->assertSame(100.0, (float) OfflineProgress::query()->firstOrFail()->progress_percent);
    }

    public function test_offline_quiz_answer_is_saved_and_idempotent_queue_is_respected(): void
    {
        $payload = [
            'device_id' => 'android-device-1',
            'items' => [[
                'client_uuid' => 'quiz-001',
                'operation' => 'quiz.answer',
                'payload' => [
                    'question_id' => 99,
                    'answer_data' => ['choice' => 'A'],
                    'client_updated_at' => now()->toISOString(),
                ],
            ]],
        ];

        $first = $this->withMobileHeaders()->postJson('/api/v1/mobile/sync', $payload);
        $second = $this->withMobileHeaders()->postJson('/api/v1/mobile/sync', $payload);

        $first->assertOk()->assertJsonPath('synced', 1);
        $second->assertOk()->assertJsonPath('items.0.mode', 'already_synced');

        $this->assertSame(1, OfflineQueue::query()->where('client_uuid', 'quiz-001')->count());
        $this->assertSame(1, OfflineQuizAnswer::query()->where('question_id', 99)->count());
    }

    private function withMobileHeaders(): self
    {
        $user = LmsUser::query()->where('user_type', 'student')->first() ?? LmsUser::query()->firstOrFail();

        return $this->withHeaders([
            'X-Tenant-Code' => 'VABIS',
            'X-Demo-User-Email' => $user->email,
        ]);
    }
}
