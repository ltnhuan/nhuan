<?php

namespace App\Services;

use App\Models\CareerProfile;
use App\Models\CareerTimelineEvent;
use App\Models\CompetencyRecord;
use App\Models\DigitalPortfolio;
use App\Models\EmployerProfileView;
use App\Models\GradeSummary;
use App\Models\LearnerSkill;
use App\Models\LmsUser;
use App\Models\PortfolioItem;
use App\Models\SkillDefinition;
use Illuminate\Support\Str;

class CareerPortfolioService
{
    public function ensureProfile(int $tenantId, int $userId, array $data = []): CareerProfile
    {
        $user = LmsUser::query()->findOrFail($userId);
        $slug = $data['public_slug'] ?? Str::slug(($user->code ?: 'learner').'-'.$user->full_name);
        $defaults = [
                'headline' => 'Hồ sơ nghề nghiệp '.$user->full_name,
                'summary' => 'Hồ sơ học tập số phục vụ thực tập, tuyển dụng và cựu sinh viên.',
                'public_slug' => $slug,
                'public_url' => '/portfolio/'.$slug,
                'qr_payload' => config('app.url', 'http://localhost').'/portfolio/'.$slug,
                'visibility' => 'private',
                'lifecycle_status' => $user->user_type === 'student' ? 'student' : 'alumni',
                'resume_data' => [],
                'settings' => ['show_grades' => true, 'show_approval_status' => true],
            ];
        $profile = CareerProfile::query()->firstOrCreate(['tenant_id' => $tenantId, 'user_id' => $userId], array_replace($defaults, $data));
        if ($data) {
            $profile->fill($data)->save();
        }
        DigitalPortfolio::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId],
            ['career_profile_id' => $profile->id, 'title' => 'Digital Portfolio - '.$user->full_name, 'summary' => $profile->summary, 'status' => 'published', 'settings' => []]
        );
        return $profile->fresh(['portfolio.items']);
    }

    public function addPortfolioItem(DigitalPortfolio $portfolio, array $data): PortfolioItem
    {
        $verificationCode = $data['verification_code'] ?? null;
        if (! $verificationCode && ($data['item_type'] ?? null) === 'certificate') {
            $verificationCode = strtoupper(Str::random(10));
        }
        $item = PortfolioItem::query()->create(array_replace([
            'tenant_id' => $portfolio->tenant_id,
            'portfolio_id' => $portfolio->id,
            'user_id' => $portfolio->user_id,
            'visibility' => 'private',
            'status' => 'draft',
            'metadata' => [],
        ], $data, [
            'tenant_id' => $portfolio->tenant_id,
            'portfolio_id' => $portfolio->id,
            'user_id' => $portfolio->user_id,
            'verification_code' => $verificationCode,
        ]));
        $this->recalculatePortfolioScore($portfolio);
        return $item;
    }

    public function upsertSkill(int $tenantId, int $userId, int $skillDefinitionId, float $score, array $data = []): LearnerSkill
    {
        return LearnerSkill::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId, 'skill_definition_id' => $skillDefinitionId],
            array_replace(['score' => min(100, max(0, $score)), 'source' => 'manual', 'assessed_at' => now()], $data)
        );
    }

    public function dashboard(int $tenantId, int $userId): array
    {
        $profile = $this->ensureProfile($tenantId, $userId);
        $portfolio = $profile->portfolio ?: DigitalPortfolio::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->first();
        $skills = $this->skillMatrix($tenantId, $userId);
        $latestGrade = GradeSummary::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->latest('updated_at')->first();
        $certificates = PortfolioItem::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->where('item_type', 'certificate')->where('status', 'published')->count();
        return [
            'profile' => $profile,
            'portfolio' => $portfolio?->load('items'),
            'grades' => ['percent' => $latestGrade?->percent, 'letter_grade' => $latestGrade?->letter_grade, 'pass_status' => $latestGrade?->pass_status],
            'skills' => $skills,
            'certificates' => $certificates,
            'portfolio_score' => (float) ($portfolio?->portfolio_score ?? 0),
            'timeline' => CareerTimelineEvent::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->orderBy('event_date')->get(),
            'competencies' => CompetencyRecord::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->latest()->get(),
        ];
    }

    public function skillMatrix(int $tenantId, int $userId): array
    {
        $skills = LearnerSkill::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->with('definition')->get();
        $groups = $skills->groupBy(fn ($skill) => $skill->definition?->category ?? 'other');
        return $groups->map(fn ($items, $category) => [
            'category' => $category,
            'score' => round($items->avg('score'), 2),
            'skills' => $items->map(fn ($skill) => ['name' => $skill->definition?->name, 'score' => (float) $skill->score, 'source' => $skill->source])->values(),
        ])->values()->all();
    }

    public function publicProfile(int $tenantId, string $slug, ?array $viewer = null): array
    {
        $profile = CareerProfile::query()->where('tenant_id', $tenantId)->where('public_slug', $slug)->where('visibility', 'public')->firstOrFail();
        if ($viewer) {
            EmployerProfileView::query()->create(['tenant_id'=>$tenantId,'user_id'=>$profile->user_id,'employer_name'=>$viewer['employer_name'] ?? null,'viewer_email'=>$viewer['viewer_email'] ?? null,'action'=>'viewed','metadata'=>[],'created_at'=>now()]);
        }
        return $this->dashboard($tenantId, $profile->user_id);
    }

    public function employerSearch(int $tenantId, array $filters = [])
    {
        $query = CareerProfile::query()->where('tenant_id', $tenantId)->where('visibility', 'public')->with('user');
        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($builder) use ($q) {
                $builder->where('headline', 'like', "%{$q}%")->orWhere('summary', 'like', "%{$q}%")->orWhereHas('user', fn ($u) => $u->where('full_name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"));
            });
        }
        if (! empty($filters['lifecycle_status'])) {
            $query->where('lifecycle_status', $filters['lifecycle_status']);
        }
        return $query->latest('updated_at')->paginate($filters['per_page'] ?? 25);
    }

    public function verifyCertificate(int $tenantId, string $code): array
    {
        $item = PortfolioItem::query()->where('tenant_id', $tenantId)->where('verification_code', $code)->where('item_type', 'certificate')->firstOrFail();
        return ['valid' => $item->status === 'published', 'certificate' => $item, 'learner' => LmsUser::query()->find($item->user_id)];
    }

    public function recalculatePortfolioScore(DigitalPortfolio $portfolio): float
    {
        $published = $portfolio->items()->where('status', 'published')->count();
        $public = $portfolio->items()->where('visibility', 'public')->count();
        $certificates = $portfolio->items()->where('item_type', 'certificate')->where('status', 'published')->count();
        $score = min(100, ($published * 8) + ($public * 4) + ($certificates * 10));
        $portfolio->forceFill(['portfolio_score' => $score])->save();
        return (float) $score;
    }

    public function defaultSkillDefinitions(int $tenantId): void
    {
        foreach ([
            'Kỹ năng nghề' => ['Vận hành thiết bị', 'An toàn lao động', 'Giải quyết sự cố'],
            'Kỹ năng mềm' => ['Giao tiếp', 'Làm việc nhóm', 'Kỷ luật nghề nghiệp'],
            'AI Skills' => ['Prompting', 'AI assisted research', 'AI safety'],
            'Digital Skills' => ['Office productivity', 'Data literacy', 'Digital collaboration'],
            'Ngoại ngữ' => ['English communication', 'Technical vocabulary'],
        ] as $category => $names) {
            foreach ($names as $name) {
                SkillDefinition::query()->updateOrCreate(['tenant_id'=>$tenantId,'category'=>$category,'name'=>$name], ['description'=>$category.' - '.$name, 'level_scale'=>'0_100', 'metadata'=>[]]);
            }
        }
    }
}
