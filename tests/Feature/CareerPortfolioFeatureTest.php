<?php

namespace Tests\Feature;

use App\Models\CareerProfile;
use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\CourseSection;
use App\Models\DigitalPortfolio;
use App\Models\Enrollment;
use App\Models\LmsUser;
use App\Models\SkillDefinition;
use App\Services\CareerPortfolioService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareerPortfolioFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        app(CareerPortfolioService::class)->defaultSkillDefinitions(1);
    }

    public function test_create_profile_and_add_portfolio_item(): void
    {
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        $profile = app(CareerPortfolioService::class)->ensureProfile(1, $student->id, ['visibility' => 'public']);
        $portfolio = DigitalPortfolio::query()->where('career_profile_id', $profile->id)->firstOrFail();

        $item = app(CareerPortfolioService::class)->addPortfolioItem($portfolio, [
            'item_type' => 'certificate',
            'title' => 'Chứng chỉ an toàn lao động',
            'issuer' => 'VABIS',
            'visibility' => 'public',
            'status' => 'published',
        ]);

        $this->assertNotNull($item->verification_code);
        $this->assertGreaterThan(0, (float) $portfolio->fresh()->portfolio_score);
    }

    public function test_skill_matrix_groups_radar_categories(): void
    {
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        $skills = SkillDefinition::query()->where('category', 'AI Skills')->take(2)->get();
        foreach ($skills as $skill) {
            app(CareerPortfolioService::class)->upsertSkill(1, $student->id, $skill->id, 80);
        }

        $matrix = app(CareerPortfolioService::class)->skillMatrix(1, $student->id);

        $this->assertTrue(collect($matrix)->contains(fn ($group) => $group['category'] === 'AI Skills' && $group['score'] === 80.0));
    }

    public function test_public_portfolio_and_employer_search(): void
    {
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        $profile = app(CareerPortfolioService::class)->ensureProfile(1, $student->id, ['visibility' => 'public', 'headline' => 'AI internship candidate']);

        $public = $this->withHeader('X-Tenant-Code', 'VABIS')->getJson('/api/v1/public-portfolios/'.$profile->public_slug);
        $public->assertOk();
        $this->assertSame($profile->id, $public->json('profile.id'));

        $search = $this->withHeader('X-Tenant-Code', 'VABIS')->withHeader('X-Demo-User-Email', 'gv.lms@vabis.edu.vn')->getJson('/api/v1/employer/students?q=AI');
        $search->assertOk();
        $this->assertGreaterThanOrEqual(1, $search->json('total'));
    }

    public function test_verify_certificate(): void
    {
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        $profile = app(CareerPortfolioService::class)->ensureProfile(1, $student->id, ['visibility' => 'public']);
        $portfolio = DigitalPortfolio::query()->where('career_profile_id', $profile->id)->firstOrFail();
        $item = app(CareerPortfolioService::class)->addPortfolioItem($portfolio, [
            'item_type' => 'certificate',
            'title' => 'Certificate verify test',
            'verification_code' => 'CERT-TEST-001',
            'visibility' => 'public',
            'status' => 'published',
        ]);

        $response = $this->withHeader('X-Tenant-Code', 'VABIS')->withHeader('X-Demo-User-Email', 'gv.lms@vabis.edu.vn')->getJson('/api/v1/employer/certificates/'.$item->verification_code.'/verify');

        $response->assertOk();
        $this->assertTrue($response->json('valid'));
    }

    public function test_student_cannot_update_other_profile(): void
    {
        $students = LmsUser::query()->where('user_type', 'student')->take(2)->get();
        $profile = app(CareerPortfolioService::class)->ensureProfile(1, $students[1]->id);

        $response = $this->withHeader('X-Tenant-Code', 'VABIS')
            ->withHeader('X-Demo-User-Email', $students[0]->email)
            ->putJson('/api/v1/career/profiles/'.$profile->id, ['headline' => 'Blocked']);

        $response->assertForbidden();
    }

    public function test_student_experience_endpoints_return_learner_centers(): void
    {
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        $headers = ['X-Tenant-Code' => 'VABIS', 'X-Demo-User-Email' => 'admin.lms@vabis.edu.vn'];
        $query = '?user_id='.$student->id;

        $this->withHeaders($headers)->getJson('/api/v1/student/attendance'.$query)
            ->assertOk()
            ->assertJsonStructure([
                'student',
                'summary' => ['attendance_rate', 'present', 'absent', 'late', 'excused', 'total_sessions'],
                'charts' => ['monthly_attendance', 'course_attendance'],
                'forecast' => ['exam_eligibility', 'graduation_eligibility'],
                'alerts' => ['below_threshold', 'risk_classes'],
            ]);

        $this->withHeaders($headers)->getJson('/api/v1/student/portfolio'.$query)
            ->assertOk()
            ->assertJsonStructure([
                'profile',
                'portfolio',
                'sections' => ['projects', 'assignments', 'certificates', 'badges', 'internships', 'activities'],
                'skill_groups' => ['technical', 'soft', 'ai', 'language'],
                'charts' => ['radar', 'growth_trend'],
                'exports' => ['pdf', 'cv', 'resume'],
            ]);

        $this->withHeaders($headers)->getJson('/api/v1/student/career'.$query)
            ->assertOk()
            ->assertJsonStructure([
                'student',
                'sections' => ['internships', 'jobs', 'career_events', 'employer_invitations', 'career_readiness'],
                'metrics' => ['portfolio_score', 'skill_score', 'language_score', 'interview_readiness'],
                'ai' => ['career_recommendation'],
            ]);

        $this->withHeaders($headers)->getJson('/api/v1/student/credentials'.$query)
            ->assertOk()
            ->assertJsonStructure([
                'student',
                'certificates',
                'badges',
                'micro_credentials',
                'summary' => ['certificates', 'badges', 'micro_credentials', 'verified', 'revoked'],
                'timeline',
                'actions' => ['verify_qr', 'download', 'share'],
            ]);

        $this->withHeaders($headers)->getJson('/api/v1/students/'.$student->id.'/digital-twin')
            ->assertOk()
            ->assertJsonStructure([
                'learner',
                'digital_twin',
                'tabs' => ['learning', 'grades', 'attendance', 'risk', 'portfolio', 'skills', 'career', 'certificates'],
                'timeline_360',
                'risk_engine' => ['dropout_risk', 'fail_risk', 'attendance_risk'],
                'advisor' => ['learning_advice', 'intervention_plan', 'support_plan'],
                'views',
            ]);
    }

    public function test_student_home_learning_journey_courses_and_tasks_endpoints(): void
    {
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        $course = Course::query()->first() ?: Course::query()->create([
            'tenant_id' => 1,
            'code' => 'COURSE-STUDENT-UX',
            'title' => 'Student UX Course',
            'slug' => 'student-ux-course',
            'level' => 'college',
            'course_type' => 'online',
            'status' => 'published',
            'visibility' => 'tenant',
            'language' => 'vi',
            'settings' => [],
        ]);
        Enrollment::query()->firstOrCreate(
            ['tenant_id' => 1, 'course_id' => $course->id, 'user_id' => $student->id],
            ['status' => 'active', 'completion_percent' => 30, 'risk_score' => 25, 'source' => 'test']
        );
        $section = CourseSection::query()->firstOrCreate(
            ['tenant_id' => 1, 'course_id' => $course->id, 'title' => 'Module 1'],
            ['type' => 'unit', 'sort_order' => 1, 'status' => 'published', 'settings' => []]
        );
        CourseComponent::query()->firstOrCreate(
            ['tenant_id' => 1, 'course_id' => $course->id, 'section_id' => $section->id, 'title' => 'Video mở đầu'],
            ['component_type' => 'video', 'sort_order' => 1, 'required' => true, 'status' => 'published', 'config' => ['duration_minutes' => 10]]
        );

        $headers = ['X-Tenant-Code' => 'VABIS', 'X-Demo-User-Email' => 'admin.lms@vabis.edu.vn'];
        $query = '?user_id='.$student->id;

        $this->withHeaders($headers)->getJson('/api/v1/student-home'.$query)
            ->assertOk()
            ->assertJsonStructure([
                'student',
                'header' => ['notifications', 'ai_coach'],
                'learning_progress' => ['current_semester', 'program', 'total_credits', 'completed_credits', 'progress_percent'],
                'continue_learning',
                'upcoming_tasks',
                'today_schedule',
                'achievement' => ['badges', 'certificates', 'xp', 'streak'],
                'risk_indicator' => ['level', 'score'],
                'ai_recommendation' => ['lesson', 'quiz', 'assignment', 'message'],
            ]);

        $this->withHeaders($headers)->getJson('/api/v1/student/courses'.$query)
            ->assertOk()
            ->assertJsonStructure([
                'student',
                'sections' => ['continue_learning', 'in_progress', 'upcoming', 'completed', 'archived'],
                'search',
                'filters',
                'all',
            ]);

        $this->withHeaders($headers)->getJson('/api/v1/student/journey/'.$course->id.$query)
            ->assertOk()
            ->assertJsonStructure([
                'student',
                'course',
                'summary' => ['total_nodes', 'completed_nodes', 'current_node', 'locked_nodes'],
                'timeline',
                'flow',
            ]);

        $this->withHeaders($headers)->getJson('/api/v1/student/tasks'.$query)
            ->assertOk()
            ->assertJsonStructure([
                'student',
                'sections' => ['today', 'this_week', 'overdue', 'future'],
                'bulk' => ['mark_read', 'reminder'],
                'risk' => ['overdue_alerts'],
                'all',
            ]);
    }
}
