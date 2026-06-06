<?php

namespace Tests\Feature;

use App\Models\CareerProfile;
use App\Models\DigitalPortfolio;
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
}
