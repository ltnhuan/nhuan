<?php

namespace Tests\Feature;

use App\Models\CommunityForum;
use App\Models\Course;
use App\Models\DiscussionPost;
use App\Models\LmsUser;
use App\Services\LearningCommunityService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningCommunityFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
    }

    public function test_create_thread_extracts_mentions_and_notifications(): void
    {
        [$forum, $student, $teacher] = $this->context();
        $thread = app(LearningCommunityService::class)->createThread($forum, [
            'thread_type' => 'qna',
            'title' => 'Hỏi đáp thực hành',
            'body_html' => '<p>Nhờ @'.$teacher->code.' xem giúp checklist.</p>',
        ], $student->id);

        $post = $thread->posts()->firstOrFail();
        $this->assertDatabaseHas('discussion_mentions', ['post_id' => $post->id, 'mentioned_user_id' => $teacher->id]);
        $this->assertDatabaseHas('community_notifications', ['user_id' => $teacher->id, 'notification_type' => 'mention']);
    }

    public function test_reply_like_and_mark_correct_update_reputation(): void
    {
        [$forum, $student, $teacher] = $this->context();
        $service = app(LearningCommunityService::class);
        $thread = $service->createThread($forum, ['thread_type' => 'qna', 'title' => 'Câu hỏi', 'body_html' => '<p>Cần hỗ trợ.</p>'], $student->id);
        $reply = $service->createPost($thread, ['body_html' => '<p>Câu trả lời đúng.</p>'], $teacher->id);
        $service->react($reply, $student->id, 'upvote');
        $service->markCorrect($reply, $teacher->id);

        $this->assertTrue($reply->fresh()->is_correct_answer);
        $this->assertDatabaseHas('discussion_threads', ['id' => $thread->id, 'correct_post_id' => $reply->id]);
        $this->assertDatabaseHas('reputation_profiles', ['user_id' => $teacher->id, 'points' => 15]);
    }

    public function test_moderation_can_hide_post_and_lock_thread(): void
    {
        [$forum, $student, $teacher] = $this->context();
        $service = app(LearningCommunityService::class);
        $thread = $service->createThread($forum, ['title' => 'Topic', 'body_html' => '<p>Nội dung.</p>'], $student->id);
        $post = DiscussionPost::query()->where('thread_id', $thread->id)->firstOrFail();

        $service->moderate('post', $post->id, 'hide', $teacher->id);
        $service->moderate('thread', $thread->id, 'lock', $teacher->id);

        $this->assertSame('hidden', $post->fresh()->status);
        $this->assertTrue($thread->fresh()->is_locked);
    }

    public function test_api_analytics_returns_engagement_score(): void
    {
        [$forum, $student] = $this->context();
        app(LearningCommunityService::class)->createThread($forum, ['title' => 'Topic API', 'body_html' => '<p>Nội dung.</p>'], $student->id);

        $response = $this->withHeaders([
            'X-Tenant-Code' => 'VABIS',
            'X-Demo-User-Email' => 'admin.lms@vabis.edu.vn',
        ])->getJson('/api/v1/community/analytics');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('active_topics'));
        $this->assertGreaterThan(0, $response->json('engagement_score'));
    }

    private function context(): array
    {
        $course = Course::query()->create(['tenant_id'=>1,'code'=>'COM-'.uniqid(),'title'=>'Khóa cộng đồng','slug'=>'khoa-cong-dong-'.uniqid(),'level'=>'college','course_type'=>'blended','status'=>'published','visibility'=>'internal','language'=>'vi','estimated_hours'=>10,'owner_id'=>1,'settings'=>[]]);
        $teacher = LmsUser::query()->where('user_type', 'teacher')->firstOrFail();
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        $forum = CommunityForum::query()->create(['tenant_id'=>1,'forum_type'=>'course','course_id'=>$course->id,'title'=>'Forum cộng đồng','visibility'=>'course','status'=>'active','created_by'=>$teacher->id,'settings'=>[]]);

        return [$forum, $student, $teacher];
    }
}
