<?php

namespace App\Services;

use App\Models\ClassSection;
use App\Models\Cohort;
use App\Models\CareerProfile;
use App\Models\CareerTimelineEvent;
use App\Models\CompetencyRecord;
use App\Models\DigitalPortfolio;
use App\Models\EmployerProfileView;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Models\ExamResult;
use App\Models\GradeSummary;
use App\Models\LearnerSkill;
use App\Models\LmsUser;
use App\Models\PortfolioItem;
use App\Models\SkillDefinition;
use App\Models\UserCourseProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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

    public function digitalTwinIdp(int $tenantId, int $userId): array
    {
        $user = LmsUser::query()->findOrFail($userId);
        $profile = $this->ensureProfile($tenantId, $userId);
        $portfolio = $profile->portfolio ?: DigitalPortfolio::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->first();
        $skills = collect($this->skillMatrix($tenantId, $userId));
        $progress = UserCourseProgress::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->with('course')->get();
        $enrollments = Enrollment::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->with('course')->get();
        $results = ExamResult::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->latest('updated_at')->get();
        $attempts = ExamAttempt::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->with('exam')->latest('updated_at')->limit(10)->get();

        $completion = round($progress->avg('progress_percent') ?? $enrollments->avg('completion_percent') ?? 0, 1);
        $examPercent = round($results->avg('percent') ?? $attempts->avg(fn ($attempt) => $attempt->max_score > 0 ? ($attempt->score / $attempt->max_score) * 100 : null) ?? 0, 1);
        $risk = round($enrollments->avg('risk_score') ?? 0, 1);
        $skillScore = round($skills->avg('score') ?? 0, 1);
        $portfolioScore = round((float) ($portfolio?->portfolio_score ?? 0), 1);
        $readiness = round(($completion * .28) + ($examPercent * .28) + ($skillScore * .24) + ($portfolioScore * .12) + ((100 - $risk) * .08), 1);

        $skillGaps = $skills->map(fn ($group) => [
            'name' => $group['category'],
            'score' => (float) $group['score'],
            'gap' => max(0, 85 - (float) $group['score']),
            'priority' => (float) $group['score'] < 70 ? 'high' : ((float) $group['score'] < 82 ? 'medium' : 'low'),
        ])->sortByDesc('gap')->values()->all();

        $weakCourses = $progress->sortBy('progress_percent')->take(4)->map(fn ($item) => [
            'course' => $item->course?->title ?? 'Khóa học',
            'progress' => (float) $item->progress_percent,
            'risk_level' => $item->risk_level ?? 'normal',
            'last_accessed_at' => $item->last_accessed_at?->toDateString(),
        ])->values()->all();

        $idpActions = $this->idpActions($completion, $examPercent, $skillGaps, $weakCourses);
        return [
            'learner' => ['id' => $user->id, 'code' => $user->code, 'name' => $user->full_name, 'email' => $user->email],
            'digital_twin' => [
                'readiness_score' => $readiness,
                'learning_momentum' => $completion >= 75 ? 'good' : ($completion >= 45 ? 'watch' : 'risk'),
                'exam_mastery' => $examPercent >= 80 ? 'strong' : ($examPercent >= 60 ? 'developing' : 'weak'),
                'portfolio_maturity' => $portfolioScore >= 80 ? 'published-ready' : ($portfolioScore >= 45 ? 'needs-evidence' : 'thin-profile'),
                'risk_score' => $risk,
            ],
            'metrics' => [
                ['label' => 'Hoàn thành học tập', 'value' => $completion, 'suffix' => '%'],
                ['label' => 'Năng lực qua bài thi', 'value' => $examPercent, 'suffix' => '%'],
                ['label' => 'Kỹ năng IDP', 'value' => $skillScore, 'suffix' => '/100'],
                ['label' => 'Portfolio', 'value' => $portfolioScore, 'suffix' => '/100'],
            ],
            'courses' => $weakCourses,
            'skills' => $skillGaps,
            'exam_results' => $results->take(6)->map(fn ($result) => [
                'exam_id' => $result->exam_id,
                'percent' => (float) $result->percent,
                'pass_status' => $result->pass_status,
                'published' => (bool) $result->published,
            ])->values(),
            'attempts' => $attempts->map(fn ($attempt) => [
                'exam' => $attempt->exam?->title ?? 'Bài thi',
                'score' => (float) $attempt->score,
                'max_score' => (float) $attempt->max_score,
                'status' => $attempt->status,
                'submitted_at' => $attempt->submitted_at?->toDateTimeString(),
            ])->values(),
            'idp' => [
                'goal' => $readiness >= 80 ? 'Sẵn sàng thực tập/tuyển dụng có kiểm chứng' : 'Tăng tốc năng lực lõi trước khi publish hồ sơ',
                'horizon_days' => 90,
                'actions' => $idpActions,
            ],
            'analysis_prompt' => $this->idpPrompt(),
        ];
    }

    public function digitalTwinOverview(int $tenantId, array $filters = []): array
    {
        $filters = $this->normalizeDigitalTwinFilters($filters);
        $baseQuery = $this->filteredDigitalTwinEnrollments($tenantId, $filters);

        $summaryBuilder = (clone $baseQuery)
            ->select('enrollments.user_id')
            ->selectRaw('COUNT(*) as enrollment_count')
            ->selectRaw('AVG(enrollments.completion_percent) as avg_completion_percent')
            ->selectRaw('AVG(enrollments.risk_score) as avg_risk_score')
            ->selectRaw('MAX(enrollments.updated_at) as last_activity_at')
            ->selectRaw('MAX(enrollments.enrolled_at) as last_enrolled_at')
            ->groupBy('enrollments.user_id')
            ->orderByDesc(DB::raw('MAX(enrollments.updated_at)'))
            ->orderBy('enrollments.user_id');

        $allSummaryRows = (clone $summaryBuilder)->get();
        $summaryPage = (clone $summaryBuilder)->paginate($filters['per_page'], ['*'], 'page', $filters['page']);

        $candidateIds = $summaryPage->getCollection()->pluck('user_id')->values()->all();
        $users = LmsUser::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $candidateIds)
            ->get()
            ->keyBy('id');

        $latestEnrollments = collect();
        if ($candidateIds !== []) {
            $latestEnrollments = $this->filteredDigitalTwinEnrollments($tenantId, $filters, true)
                ->whereIn('enrollments.user_id', $candidateIds)
                ->orderByDesc('enrollments.updated_at')
                ->orderByDesc('enrollments.id')
                ->get()
                ->groupBy('user_id')
                ->map(fn ($items) => $items->first());
        }

        $candidateRows = $summaryPage->getCollection()->map(function ($row) use ($users, $latestEnrollments) {
            $user = $users->get((int) $row->user_id);
            $enrollment = $latestEnrollments->get((int) $row->user_id);
            $completion = round((float) ($row->avg_completion_percent ?? 0), 1);
            $risk = round((float) ($row->avg_risk_score ?? 0), 1);
            $readiness = round(($completion * 0.6) + ((100 - $risk) * 0.4), 1);

            return [
                'user' => [
                    'id' => $user?->id,
                    'code' => $user?->code,
                    'full_name' => $user?->full_name,
                    'email' => $user?->email,
                    'avatar_url' => $user?->avatar_url,
                    'user_type' => $user?->user_type,
                ],
                'stats' => [
                    'enrollment_count' => (int) $row->enrollment_count,
                    'avg_completion_percent' => $completion,
                    'avg_risk_score' => $risk,
                    'last_activity_at' => $row->last_activity_at,
                    'last_enrolled_at' => $row->last_enrolled_at,
                    'readiness_score' => $readiness,
                ],
                'primary_enrollment' => $enrollment ? [
                    'id' => $enrollment->id,
                    'status' => $enrollment->status,
                    'completion_percent' => (float) $enrollment->completion_percent,
                    'risk_score' => (float) $enrollment->risk_score,
                    'enrolled_at' => $enrollment->enrolled_at?->toDateTimeString(),
                    'updated_at' => $enrollment->updated_at?->toDateTimeString(),
                    'course' => [
                        'id' => $enrollment->course?->id,
                        'code' => $enrollment->course?->code,
                        'title' => $enrollment->course?->title,
                    ],
                    'class_section' => $enrollment->classSection ? [
                        'id' => $enrollment->classSection->id,
                        'code' => $enrollment->classSection->code,
                        'name' => $enrollment->classSection->name,
                        'starts_at' => $enrollment->classSection->starts_at?->toDateTimeString(),
                        'ends_at' => $enrollment->classSection->ends_at?->toDateTimeString(),
                    ] : null,
                    'cohort' => $enrollment->cohort ? [
                        'id' => $enrollment->cohort->id,
                        'code' => $enrollment->cohort->code,
                        'name' => $enrollment->cohort->name,
                        'start_date' => $enrollment->cohort->start_date?->toDateString(),
                        'end_date' => $enrollment->cohort->end_date?->toDateString(),
                    ] : null,
                    'cohort_group' => $enrollment->cohortGroup ? [
                        'id' => $enrollment->cohortGroup->id,
                        'code' => $enrollment->cohortGroup->code,
                        'name' => $enrollment->cohortGroup->name,
                    ] : null,
                ] : null,
            ];
        })->values();

        $selectedUserId = (int) ($filters['user_id'] ?? 0);
        if (! $selectedUserId) {
            $selectedUserId = (int) data_get($candidateRows->first(), 'user.id', 0);
        }
        $selectedCandidate = $selectedUserId ? $this->digitalTwinIdp($tenantId, $selectedUserId) : null;

        $byClass = (clone $baseQuery)
            ->leftJoin('class_sections as class_sections_breakdown', 'class_sections_breakdown.id', '=', 'enrollments.class_section_id')
            ->leftJoin('courses as courses_breakdown', 'courses_breakdown.id', '=', 'class_sections_breakdown.course_id')
            ->selectRaw('class_sections_breakdown.id as id, class_sections_breakdown.code as code, class_sections_breakdown.name as name, courses_breakdown.title as course_title, COUNT(DISTINCT enrollments.user_id) as candidates_count, AVG(enrollments.completion_percent) as avg_completion_percent, AVG(enrollments.risk_score) as avg_risk_score, MAX(enrollments.updated_at) as last_activity_at')
            ->groupBy('class_sections_breakdown.id', 'class_sections_breakdown.code', 'class_sections_breakdown.name', 'courses_breakdown.title')
            ->orderByDesc('candidates_count')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'course_title' => $row->course_title,
                'candidates_count' => (int) $row->candidates_count,
                'avg_completion_percent' => round((float) $row->avg_completion_percent, 1),
                'avg_risk_score' => round((float) $row->avg_risk_score, 1),
                'last_activity_at' => $row->last_activity_at,
            ])->values();

        $byCohort = (clone $baseQuery)
            ->leftJoin('cohorts as cohorts_breakdown', 'cohorts_breakdown.id', '=', 'enrollments.cohort_id')
            ->selectRaw('cohorts_breakdown.id as id, cohorts_breakdown.code as code, cohorts_breakdown.name as name, COUNT(DISTINCT enrollments.user_id) as candidates_count, AVG(enrollments.completion_percent) as avg_completion_percent, AVG(enrollments.risk_score) as avg_risk_score, MAX(enrollments.updated_at) as last_activity_at')
            ->groupBy('cohorts_breakdown.id', 'cohorts_breakdown.code', 'cohorts_breakdown.name')
            ->orderByDesc('candidates_count')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'candidates_count' => (int) $row->candidates_count,
                'avg_completion_percent' => round((float) $row->avg_completion_percent, 1),
                'avg_risk_score' => round((float) $row->avg_risk_score, 1),
                'last_activity_at' => $row->last_activity_at,
            ])->values();

        $yearExpression = 'EXTRACT(YEAR FROM COALESCE(class_sections_breakdown.starts_at, cohorts_breakdown.start_date, enrollments.created_at))::int';
        $byYear = (clone $baseQuery)
            ->leftJoin('class_sections as class_sections_breakdown', 'class_sections_breakdown.id', '=', 'enrollments.class_section_id')
            ->leftJoin('cohorts as cohorts_breakdown', 'cohorts_breakdown.id', '=', 'enrollments.cohort_id')
            ->selectRaw("{$yearExpression} as academic_year, COUNT(DISTINCT enrollments.user_id) as candidates_count, AVG(enrollments.completion_percent) as avg_completion_percent, AVG(enrollments.risk_score) as avg_risk_score")
            ->groupByRaw($yearExpression)
            ->orderByDesc('academic_year')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'academic_year' => $row->academic_year ? (int) $row->academic_year : null,
                'candidates_count' => (int) $row->candidates_count,
                'avg_completion_percent' => round((float) $row->avg_completion_percent, 1),
                'avg_risk_score' => round((float) $row->avg_risk_score, 1),
            ])->values();

        $rowsArray = $allSummaryRows->map(function ($row) use ($latestEnrollments, $users) {
            $enrollment = $latestEnrollments->get((int) $row->user_id);
            $completion = round((float) ($row->avg_completion_percent ?? 0), 1);
            $risk = round((float) ($row->avg_risk_score ?? 0), 1);

            return [
                'user' => $users->get((int) $row->user_id),
                'stats' => [
                    'avg_completion_percent' => $completion,
                    'avg_risk_score' => $risk,
                ],
                'primary_enrollment' => $enrollment ? ['status' => $enrollment->status] : null,
            ];
        })->all();
        $statusCounts = collect($rowsArray)
            ->groupBy(fn ($row) => $row['primary_enrollment']['status'] ?? 'unknown')
            ->map(fn ($items, $status) => ['status' => $status, 'count' => $items->count()])
            ->values();

        $completionAverage = round((float) collect($rowsArray)->avg(fn ($row) => $row['stats']['avg_completion_percent'] ?? 0), 1);
        $riskAverage = round((float) collect($rowsArray)->avg(fn ($row) => $row['stats']['avg_risk_score'] ?? 0), 1);
        $activeCandidates = collect($rowsArray)->filter(fn ($row) => in_array($row['primary_enrollment']['status'] ?? '', ['active', 'enrolled', 'in_progress'], true))->count();
        $completedCandidates = collect($rowsArray)->filter(fn ($row) => ($row['primary_enrollment']['status'] ?? '') === 'completed')->count();
        $atRiskCandidates = collect($rowsArray)->filter(fn ($row) => (float) ($row['stats']['avg_risk_score'] ?? 0) >= 60 || in_array($row['primary_enrollment']['status'] ?? '', ['suspended', 'withdrawn', 'expired'], true))->count();

        return [
            'scope' => 'overview',
            'filters' => $filters,
            'summary' => [
                'total_candidates' => (int) $summaryPage->total(),
                'active_candidates' => $activeCandidates,
                'completed_candidates' => $completedCandidates,
                'at_risk_candidates' => $atRiskCandidates,
                'avg_completion_percent' => $completionAverage,
                'avg_risk_score' => $riskAverage,
            ],
            'status_breakdown' => $statusCounts,
            'breakdowns' => [
                'classes' => $byClass,
                'cohorts' => $byCohort,
                'years' => $byYear,
            ],
            'options' => [
                'classes' => ClassSection::query()
                    ->where('tenant_id', $tenantId)
                    ->with(['course:id,title,code', 'cohort:id,code,name'])
                    ->orderByDesc('starts_at')
                    ->limit(60)
                    ->get()
                    ->map(fn ($section) => [
                        'id' => $section->id,
                        'label' => $section->name,
                        'code' => $section->code,
                        'course_title' => $section->course?->title,
                        'cohort_name' => $section->cohort?->name,
                    ])->values(),
                'cohorts' => Cohort::query()
                    ->where('tenant_id', $tenantId)
                    ->orderByDesc('start_date')
                    ->limit(60)
                    ->get()
                    ->map(fn ($cohort) => [
                        'id' => $cohort->id,
                        'label' => $cohort->name,
                        'code' => $cohort->code,
                        'start_date' => $cohort->start_date?->toDateString(),
                    ])->values(),
                'years' => $byYear->pluck('academic_year')->filter()->values(),
            ],
            'candidates' => [
                'data' => $candidateRows,
                'meta' => [
                    'current_page' => $summaryPage->currentPage(),
                    'last_page' => $summaryPage->lastPage(),
                    'per_page' => $summaryPage->perPage(),
                    'total' => $summaryPage->total(),
                ],
            ],
            'selected_candidate_id' => $selectedUserId ?: null,
            'selected_candidate' => $selectedCandidate,
        ];
    }

    private function filteredDigitalTwinEnrollments(int $tenantId, array $filters, bool $withRelations = false): Builder
    {
        $query = Enrollment::query()->where('tenant_id', $tenantId);
        if ($withRelations) {
            $query->with([
                'learner:id,full_name,email,code,avatar_url,user_type,status',
                'course:id,code,title',
                'classSection:id,course_id,cohort_id,cohort_group_id,code,name,starts_at,ends_at,status',
                'classSection.course:id,code,title',
                'classSection.cohort:id,code,name,start_date,end_date,status',
                'classSection.cohortGroup:id,code,name,status',
                'cohort:id,code,name,start_date,end_date,status',
                'cohortGroup:id,code,name,status',
            ]);
        }

        $this->applyDigitalTwinFilters($query, $filters);
        return $query;
    }

    private function applyDigitalTwinFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $query->where(function ($builder) use ($q) {
                if (is_numeric($q)) {
                    $builder->orWhere('enrollments.user_id', (int) $q);
                }

                $builder->orWhere('sis_enrollment_id', 'like', "%{$q}%")
                    ->orWhereHas('learner', function ($learner) use ($q) {
                        $learner->where('full_name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%")
                            ->orWhere('code', 'like', "%{$q}%");
                    })
                    ->orWhereHas('course', function ($course) use ($q) {
                        $course->where('title', 'like', "%{$q}%")
                            ->orWhere('code', 'like', "%{$q}%");
                    })
                    ->orWhereHas('classSection', function ($section) use ($q) {
                        $section->where('name', 'like', "%{$q}%")
                            ->orWhere('code', 'like', "%{$q}%");
                    });
            });
        }

        if (! empty($filters['class_section_id'])) {
            $query->where('class_section_id', (int) $filters['class_section_id']);
        }

        if (! empty($filters['cohort_id'])) {
            $cohortId = (int) $filters['cohort_id'];
            $query->where(function ($builder) use ($cohortId) {
                $builder->where('cohort_id', $cohortId)
                    ->orWhereHas('classSection', fn ($section) => $section->where('cohort_id', $cohortId));
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['year'])) {
            $year = (int) $filters['year'];
            $query->where(function ($builder) use ($year) {
                $builder->whereYear('enrolled_at', $year)
                    ->orWhereYear('created_at', $year)
                    ->orWhereHas('classSection', function ($section) use ($year) {
                        $section->whereYear('starts_at', $year)
                            ->orWhereHas('cohort', fn ($cohort) => $cohort->whereYear('start_date', $year));
                    })
                    ->orWhereHas('cohort', fn ($cohort) => $cohort->whereYear('start_date', $year));
            });
        }
    }

    private function normalizeDigitalTwinFilters(array $filters): array
    {
        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'class_section_id' => (int) ($filters['class_section_id'] ?? 0) ?: null,
            'cohort_id' => (int) ($filters['cohort_id'] ?? 0) ?: null,
            'year' => (int) ($filters['year'] ?? 0) ?: null,
            'status' => $filters['status'] ?? null,
            'user_id' => (int) ($filters['user_id'] ?? 0) ?: null,
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'per_page' => min(50, max(5, (int) ($filters['per_page'] ?? 12))),
        ];
    }

    private function idpActions(float $completion, float $examPercent, array $skillGaps, $weakCourses): array
    {
        $actions = [];
        if ($completion < 80) {
            $actions[] = ['phase' => '30 ngày', 'focus' => 'Bù tiến độ học', 'action' => 'Học lại các bài có tiến độ thấp và hoàn thành tối thiểu 80% component bắt buộc.', 'evidence' => $weakCourses[0]['course'] ?? 'Khóa học đang học'];
        }
        if ($examPercent < 75) {
            $actions[] = ['phase' => '30 ngày', 'focus' => 'Củng cố năng lực thi', 'action' => 'Xem lại kết quả thi, lọc nhóm câu sai và làm lại quiz media demo.', 'evidence' => 'ExamResult + ExamAttempt'];
        }
        foreach (array_slice($skillGaps, 0, 3) as $gap) {
            $actions[] = ['phase' => '60 ngày', 'focus' => $gap['name'], 'action' => 'Thực hiện 1 artifact portfolio và 1 đánh giá kỹ năng để kéo điểm lên mốc 85.', 'evidence' => 'LearnerSkill + PortfolioItem'];
        }
        $actions[] = ['phase' => '90 ngày', 'focus' => 'Publish năng lực', 'action' => 'Cập nhật public portfolio, gắn chứng chỉ và chuẩn bị hồ sơ employer view.', 'evidence' => 'DigitalPortfolio + Credential wallet'];
        return $actions;
    }

    private function idpPrompt(): string
    {
        return "Bạn là cố vấn IDP trong LMS. Phân tích digital twin của người học dựa trên dữ liệu thật: tiến độ khóa học, kết quả thi, kỹ năng, portfolio, chứng chỉ và risk_score. Không bịa dữ liệu. Hãy trả về: 1) nhận định năng lực hiện tại, 2) gap theo kỹ năng và môn học, 3) mục tiêu 30/60/90 ngày, 4) hành động học lại/luyện thi/bổ sung portfolio, 5) bằng chứng cần thu thập để sẵn sàng thực tập hoặc tuyển dụng.";
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
