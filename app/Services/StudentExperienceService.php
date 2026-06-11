<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\BadgeIssue;
use App\Models\CertificateIssue;
use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamEnrollment;
use App\Models\ExamResult;
use App\Models\GradeSummary;
use App\Models\LearningCompletion;
use App\Models\LearningPathRule;
use App\Models\LearnerEligibilitySummary;
use App\Models\LiveSession;
use App\Models\LmsUser;
use App\Models\MicroCredential;
use App\Models\PortfolioItem;
use App\Models\SurveyCampaign;
use App\Models\UserCourseProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class StudentExperienceService
{
    public function __construct(
        private readonly CareerPortfolioService $career,
        private readonly DigitalCredentialService $credentials,
    ) {}

    public function home(int $tenantId, LmsUser $student): array
    {
        $courses = $this->courses($tenantId, $student);
        $tasks = $this->tasks($tenantId, $student);
        $grades = GradeSummary::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->latest('updated_at')
            ->get();
        $attendance = $this->attendance($tenantId, $student);
        $credentials = $this->credentials($tenantId, $student);
        $enrollments = $this->studentEnrollments($tenantId, $student);

        $totalCredits = max(1, (int) $grades->sum(fn ($grade) => (int) data_get($grade, 'metadata.credits', 3)));
        $completedCredits = (int) $grades
            ->whereIn('pass_status', ['passed', 'pass'])
            ->sum(fn ($grade) => (int) data_get($grade, 'metadata.credits', 3));
        if ($grades->isEmpty()) {
            $totalCredits = max(1, $enrollments->count() * 3);
            $completedCredits = $enrollments->whereIn('status', ['completed', 'passed'])->count() * 3;
        }

        $risk = $this->riskIndicator($enrollments, (float) data_get($attendance, 'summary.attendance_rate', 0));
        $nextTask = collect($tasks['sections']['today'])
            ->merge($tasks['sections']['this_week'])
            ->merge($tasks['sections']['future'])
            ->first();
        $nextCourse = collect($courses['sections']['continue_learning'])->first()
            ?: collect($courses['sections']['in_progress'])->first();

        return [
            'student' => $this->studentSummary($student) + [
                'class' => data_get($enrollments->first(), 'classSection.name') ?? data_get($enrollments->first(), 'classSection.code'),
                'major' => data_get($enrollments->first(), 'course.academicUnit.name') ?? 'Chương trình đào tạo',
                'cohort' => data_get($enrollments->first(), 'cohort.name') ?? data_get($enrollments->first(), 'cohort.code') ?? 'Khóa hiện tại',
                'campus' => data_get($student, 'metadata.campus') ?? 'Main Campus',
                'avatar_url' => $student->avatar_url ?? null,
            ],
            'header' => [
                'notifications' => collect($tasks['sections']['overdue'])->take(3)->values(),
                'ai_coach' => 'online',
            ],
            'learning_progress' => [
                'current_semester' => data_get($grades->first(), 'metadata.semester') ?? now()->format('Y').'-'.(now()->month <= 6 ? 'S1' : 'S2'),
                'program' => data_get($enrollments->first(), 'course.academicUnit.name') ?? 'Chương trình đào tạo',
                'total_credits' => $totalCredits,
                'completed_credits' => $completedCredits,
                'progress_percent' => round($completedCredits * 100 / $totalCredits, 1),
            ],
            'continue_learning' => collect($courses['sections']['continue_learning'])->take(5)->values(),
            'upcoming_tasks' => collect($tasks['all'])->take(8)->values(),
            'today_schedule' => collect($tasks['sections']['today'])
                ->filter(fn ($task) => in_array($task['type'], ['live_session', 'exam', 'deadline', 'assignment'], true))
                ->values(),
            'achievement' => [
                'badges' => (int) data_get($credentials, 'summary.badges', 0),
                'certificates' => (int) data_get($credentials, 'summary.certificates', 0),
                'xp' => ($completedCredits * 25) + ((int) data_get($credentials, 'summary.badges', 0) * 100) + ((int) data_get($credentials, 'summary.certificates', 0) * 200),
                'streak' => $this->attendanceStreak($tenantId, $student),
            ],
            'risk_indicator' => $risk,
            'ai_recommendation' => [
                'lesson' => $nextCourse ? 'Học tiếp '.$nextCourse['next_lesson'] : 'Mở khóa học gần nhất để bắt đầu học.',
                'quiz' => $nextTask && $nextTask['type'] === 'quiz' ? $nextTask['title'] : data_get(collect($tasks['all'])->firstWhere('type', 'quiz'), 'title'),
                'assignment' => $nextTask && $nextTask['type'] === 'assignment' ? $nextTask['title'] : data_get(collect($tasks['all'])->firstWhere('type', 'assignment'), 'title'),
                'message' => $risk['level'] === 'critical'
                    ? 'Ưu tiên xử lý task quá hạn và liên hệ cố vấn học tập.'
                    : 'Tiếp tục bài học gần nhất và hoàn thành task có deadline sớm nhất.',
            ],
            'performance' => ['target_ms' => 1000],
        ];
    }

    public function courses(int $tenantId, LmsUser $student, array $filters = []): array
    {
        $enrollments = $this->studentEnrollments($tenantId, $student);
        $progress = UserCourseProgress::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->with('course.owner')
            ->get()
            ->keyBy('course_id');

        $cards = $enrollments
            ->map(fn (Enrollment $enrollment) => $this->courseCard($enrollment, $progress->get($enrollment->course_id)))
            ->filter()
            ->values();

        $cards = $this->filterCourseCards($cards, $filters);

        return [
            'student' => $this->studentSummary($student),
            'sections' => [
                'continue_learning' => $cards->filter(fn ($card) => in_array($card['status'], ['active', 'in_progress', 'enrolled'], true))
                    ->sortByDesc('last_accessed_at')
                    ->take(10)
                    ->values(),
                'in_progress' => $cards->filter(fn ($card) => $card['progress'] > 0 && $card['progress'] < 100 && ! in_array($card['status'], ['archived', 'withdrawn', 'suspended'], true))->values(),
                'upcoming' => $cards->filter(fn ($card) => in_array($card['status'], ['pending', 'invited', 'scheduled'], true) || ($card['starts_at'] && Carbon::parse($card['starts_at'])->isFuture()))->values(),
                'completed' => $cards->filter(fn ($card) => $card['progress'] >= 100 || in_array($card['status'], ['completed', 'passed'], true))->values(),
                'archived' => $cards->filter(fn ($card) => in_array($card['status'], ['archived', 'withdrawn', 'expired', 'suspended'], true))->values(),
            ],
            'search' => [
                'q' => $filters['q'] ?? null,
                'course' => $filters['course'] ?? null,
                'teacher' => $filters['teacher'] ?? null,
                'skill' => $filters['skill'] ?? null,
            ],
            'filters' => [
                'category' => $filters['category'] ?? null,
                'faculty' => $filters['faculty'] ?? null,
                'semester' => $filters['semester'] ?? null,
            ],
            'all' => $cards,
        ];
    }

    public function journey(int $tenantId, LmsUser $student, Course $course): array
    {
        $course->loadMissing([
            'sections.components.exam',
            'sections.components.assignment',
            'sections.components.videoAsset',
            'owner',
        ]);

        $components = $course->sections
            ->sortBy('sort_order')
            ->flatMap(fn ($section) => $section->components->sortBy('sort_order')->map(fn ($component) => [$section, $component]))
            ->values();
        $completions = LearningCompletion::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->get()
            ->keyBy('component_id');
        $rules = LearningPathRule::query()
            ->where('tenant_id', $tenantId)
            ->where('course_id', $course->id)
            ->where('is_active', true)
            ->get()
            ->groupBy(fn ($rule) => $rule->target_type.'-'.$rule->target_id);

        $firstOpenFound = false;
        $previousCompleted = true;
        $nodes = $components->map(function (array $pair, int $index) use (&$firstOpenFound, &$previousCompleted, $completions, $rules) {
            [$section, $component] = $pair;
            $completion = $completions->get($component->id);
            $completed = in_array($completion?->status, ['completed', 'passed', 'approved'], true);
            $locked = ! $previousCompleted && (bool) $component->required;
            $status = $completed ? 'completed' : ($locked ? 'locked' : ($firstOpenFound ? 'locked' : 'current'));
            if ($status === 'current') {
                $firstOpenFound = true;
            }
            $previousCompleted = $completed || ! $component->required;

            $ruleKey = 'component-'.$component->id;
            $rule = $rules->get($ruleKey)?->first();

            return [
                'id' => $component->id,
                'type' => $this->journeyType($component),
                'icon' => $this->journeyIcon($component),
                'title' => $component->title,
                'section' => $section->title,
                'time' => (int) data_get($component, 'config.duration_minutes', data_get($component, 'exam.duration_minutes', 15)),
                'progress' => (float) ($completion?->progress_percent ?? ($completed ? 100 : 0)),
                'status' => $status,
                'action' => $status === 'completed' ? 'Open' : ($status === 'current' ? 'Continue' : 'Preview'),
                'unlock_condition' => $rule?->title ?? ($index === 0 ? 'Mở ngay' : 'Hoàn thành bước trước'),
                'href' => $this->componentHref($component),
            ];
        })->values();

        $certificateUnlocked = $nodes->isNotEmpty() && $nodes->where('status', 'completed')->count() === $nodes->count();
        $nodes->push([
            'id' => 'certificate-'.$course->id,
            'type' => 'certificate',
            'icon' => 'award',
            'title' => 'Certificate',
            'section' => 'Completion',
            'time' => 0,
            'progress' => $certificateUnlocked ? 100 : 0,
            'status' => $certificateUnlocked ? 'completed' : 'locked',
            'action' => $certificateUnlocked ? 'Open' : 'Preview',
            'unlock_condition' => 'Hoàn thành toàn bộ hoạt động bắt buộc',
            'href' => '/credentials/wallet',
        ]);

        return [
            'student' => $this->studentSummary($student),
            'course' => $course->only(['id', 'code', 'title', 'thumbnail_url', 'status']),
            'summary' => [
                'total_nodes' => $nodes->count(),
                'completed_nodes' => $nodes->where('status', 'completed')->count(),
                'current_node' => $nodes->firstWhere('status', 'current'),
                'locked_nodes' => $nodes->where('status', 'locked')->count(),
            ],
            'timeline' => $nodes,
            'flow' => $nodes->pluck('type')->values(),
        ];
    }

    public function tasks(int $tenantId, LmsUser $student): array
    {
        $enrollments = $this->studentEnrollments($tenantId, $student);
        $courseIds = $enrollments->pluck('course_id')->filter()->unique()->values();
        $now = now();
        $tasks = collect()
            ->merge($this->assignmentTasks($tenantId, $student, $courseIds))
            ->merge($this->examTasks($tenantId, $student, $courseIds))
            ->merge($this->liveSessionTasks($tenantId, $courseIds))
            ->merge($this->surveyTasks($tenantId, $courseIds))
            ->sortBy(fn ($task) => $task['deadline'] ? Carbon::parse($task['deadline'])->timestamp : PHP_INT_MAX)
            ->values();

        return [
            'student' => $this->studentSummary($student),
            'sections' => [
                'today' => $tasks->filter(fn ($task) => $this->taskDate($task)?->isSameDay($now))->values(),
                'this_week' => $tasks->filter(fn ($task) => $this->taskDate($task)?->greaterThan($now) && $this->taskDate($task)?->lessThanOrEqualTo($now->copy()->endOfWeek()))->values(),
                'overdue' => $tasks->filter(fn ($task) => ! in_array($task['status'], ['completed', 'submitted', 'done'], true) && $this->taskDate($task)?->lessThan($now->copy()->startOfDay()))->values(),
                'future' => $tasks->filter(fn ($task) => ! $this->taskDate($task) || $this->taskDate($task)?->greaterThan($now->copy()->endOfWeek()))->values(),
            ],
            'bulk' => ['mark_read' => true, 'reminder' => true],
            'risk' => [
                'overdue_alerts' => $tasks->filter(fn ($task) => ! in_array($task['status'], ['completed', 'submitted', 'done'], true) && $this->taskDate($task)?->isPast())->count(),
            ],
            'all' => $tasks,
        ];
    }

    public function attendance(int $tenantId, LmsUser $student): array
    {
        $records = AttendanceRecord::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->with('session:id,course_id,class_id,title,open_at,close_at,status')
            ->latest('checkin_at')
            ->get();

        $total = max(1, $records->count());
        $present = $records->whereIn('status', ['present', 'checked_in'])->count();
        $late = $records->where('status', 'late')->count();
        $excused = $records->where('status', 'excused')->count();
        $absent = $records->where('status', 'absent')->count();
        $rate = round((($present + $late + $excused) / $total) * 100, 1);

        $eligibility = LearnerEligibilitySummary::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->latest('updated_at')
            ->get();
        $courseMap = Course::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $eligibility->pluck('course_id')->filter()->unique())
            ->get(['id', 'code', 'title'])
            ->keyBy('id');

        return [
            'student' => $this->studentSummary($student),
            'summary' => [
                'attendance_rate' => $rate,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'excused' => $excused,
                'total_sessions' => $records->count(),
            ],
            'charts' => [
                'monthly_attendance' => $this->monthlyAttendance($records),
                'course_attendance' => $eligibility->map(function ($row) use ($courseMap) {
                    $course = $courseMap->get($row->course_id);

                    return [
                        'course_id' => $row->course_id,
                        'course_code' => $course?->code,
                        'course_title' => $course?->title,
                        'class_id' => $row->class_id,
                        'attendance_percent' => (float) $row->attendance_percent,
                        'absent_count' => (int) $row->absent_count,
                        'late_count' => (int) $row->late_count,
                        'eligible_for_exam' => (bool) $row->eligible_for_exam,
                        'href' => $row->course_id ? '/courses/learn?course_id='.$row->course_id : '/courses',
                    ];
                })->values(),
            ],
            'forecast' => [
                'exam_eligibility' => $eligibility->isNotEmpty() ? round($eligibility->where('eligible_for_exam', true)->count() * 100 / max(1, $eligibility->count()), 1) : ($rate >= 80 ? 100 : 0),
                'graduation_eligibility' => $rate >= 80 && $absent <= 3 ? 'on_track' : ($rate >= 70 ? 'watch' : 'risk'),
            ],
            'alerts' => [
                'below_threshold' => $rate < 80,
                'risk_classes' => $eligibility->filter(fn ($row) => ! $row->eligible_for_exam)->values(),
            ],
            'recent_records' => $records->take(12)->values(),
        ];
    }

    public function portfolio(int $tenantId, LmsUser $student): array
    {
        $dashboard = $this->career->dashboard($tenantId, $student->id);
        $items = collect(data_get($dashboard, 'portfolio.items', []));

        return $dashboard + [
            'sections' => [
                'projects' => $this->itemsOf($items, ['project']),
                'assignments' => $this->itemsOf($items, ['assignment']),
                'certificates' => $this->itemsOf($items, ['certificate']),
                'badges' => $this->itemsOf($items, ['badge']),
                'internships' => $this->itemsOf($items, ['internship']),
                'activities' => $this->itemsOf($items, ['activity', 'event']),
            ],
            'skill_groups' => [
                'technical' => $this->skillScore($dashboard['skills'] ?? [], ['Kỹ năng nghề', 'Digital Skills']),
                'soft' => $this->skillScore($dashboard['skills'] ?? [], ['Kỹ năng mềm']),
                'ai' => $this->skillScore($dashboard['skills'] ?? [], ['AI Skills']),
                'language' => $this->skillScore($dashboard['skills'] ?? [], ['Ngoại ngữ']),
            ],
            'charts' => [
                'radar' => $dashboard['skills'] ?? [],
                'growth_trend' => $this->growthTrend((float) ($dashboard['portfolio_score'] ?? 0)),
            ],
            'exports' => ['pdf' => '/career/portfolio.pdf', 'cv' => '/career/cv.pdf', 'resume' => '/career/resume.pdf'],
        ];
    }

    public function career(int $tenantId, LmsUser $student): array
    {
        $portfolio = $this->portfolio($tenantId, $student);
        $twin = $this->career->digitalTwinIdp($tenantId, $student->id);
        $skillScore = round(collect($portfolio['skills'] ?? [])->avg('score') ?? 0, 1);
        $languageScore = (float) data_get($portfolio, 'skill_groups.language.score', 0);
        $portfolioScore = (float) ($portfolio['portfolio_score'] ?? 0);
        $interview = round(($portfolioScore * .35) + ($skillScore * .35) + ($languageScore * .2) + ((float) data_get($twin, 'digital_twin.readiness_score', 0) * .1), 1);

        return [
            'student' => $this->studentSummary($student),
            'sections' => [
                'internships' => [
                    ['title' => 'Thực tập kỹ thuật số', 'company' => 'VABIS Partner', 'match_score' => min(100, $interview + 5), 'status' => 'recommended'],
                ],
                'jobs' => [
                    ['title' => 'Junior Technician', 'company' => 'Industry Demo', 'match_score' => min(100, $skillScore + 8), 'status' => 'open'],
                ],
                'career_events' => [
                    ['title' => 'Career Day', 'date' => now()->addDays(14)->toDateString(), 'status' => 'upcoming'],
                ],
                'employer_invitations' => [],
                'career_readiness' => data_get($twin, 'idp.actions', []),
            ],
            'metrics' => [
                'portfolio_score' => $portfolioScore,
                'skill_score' => $skillScore,
                'language_score' => $languageScore,
                'interview_readiness' => $interview,
            ],
            'ai' => [
                'career_recommendation' => $interview >= 80
                    ? 'Ưu tiên ứng tuyển thực tập và publish portfolio công khai.'
                    : 'Bổ sung thêm minh chứng portfolio, luyện phỏng vấn và hoàn thành skill gap trước khi ứng tuyển.',
            ],
        ];
    }

    public function credentials(int $tenantId, LmsUser $student): array
    {
        $wallet = $this->credentials->wallet($tenantId, $student->id);
        $certificates = collect($wallet['certificates'] ?? []);
        $badges = collect($wallet['badges'] ?? []);
        $portfolioItems = collect($wallet['portfolio_items'] ?? []);

        return [
            'student' => $this->studentSummary($student),
            'certificates' => $certificates,
            'badges' => $badges,
            'micro_credentials' => MicroCredential::query()->where('tenant_id', $tenantId)->where('status', 'active')->latest()->limit(20)->get(),
            'portfolio_items' => $portfolioItems,
            'summary' => [
                'certificates' => $certificates->count(),
                'badges' => $badges->count(),
                'micro_credentials' => MicroCredential::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
                'verified' => $certificates->where('status', 'issued')->count() + $badges->where('status', 'issued')->count(),
                'revoked' => $certificates->where('status', 'revoked')->count() + $badges->where('status', 'revoked')->count(),
            ],
            'timeline' => $this->credentialTimeline($certificates, $badges, $portfolioItems),
            'actions' => ['verify_qr' => true, 'download' => true, 'share' => true],
        ];
    }

    public function digitalTwin(int $tenantId, LmsUser $student): array
    {
        $twin = $this->career->digitalTwinIdp($tenantId, $student->id);
        $attendance = $this->attendance($tenantId, $student);
        $credentials = $this->credentials($tenantId, $student);

        return $twin + [
            'tabs' => [
                'learning' => data_get($twin, 'courses', []),
                'grades' => GradeSummary::query()->where('tenant_id', $tenantId)->where('user_id', $student->id)->latest('updated_at')->limit(10)->get(),
                'attendance' => $attendance,
                'risk' => data_get($twin, 'digital_twin.risk_score', 0),
                'portfolio' => $this->portfolio($tenantId, $student),
                'skills' => data_get($twin, 'skills', []),
                'career' => $this->career($tenantId, $student),
                'certificates' => $credentials,
            ],
            'timeline_360' => [
                ['stage' => 'Admission', 'status' => 'completed'],
                ['stage' => 'Enrollment', 'status' => 'completed'],
                ['stage' => 'Learning', 'status' => 'current'],
                ['stage' => 'Internship', 'status' => 'planned'],
                ['stage' => 'Graduation', 'status' => 'planned'],
            ],
            'risk_engine' => [
                'dropout_risk' => (float) data_get($twin, 'digital_twin.risk_score', 0),
                'fail_risk' => 100 - (float) data_get($twin, 'metrics.1.value', 0),
                'attendance_risk' => max(0, 80 - (float) data_get($attendance, 'summary.attendance_rate', 0)),
            ],
            'advisor' => [
                'learning_advice' => data_get($twin, 'idp.actions.0.action', 'Tiếp tục học theo lộ trình.'),
                'intervention_plan' => data_get($twin, 'idp.actions', []),
                'support_plan' => ['Cố vấn kiểm tra tiến độ hằng tuần', 'Bổ sung minh chứng portfolio', 'Hoàn thành quiz còn thiếu'],
            ],
            'views' => ['executive', 'teacher', 'advisor', 'student_self'],
        ];
    }

    private function studentSummary(LmsUser $student): array
    {
        return $student->only(['id', 'code', 'full_name', 'email', 'user_type', 'status']);
    }

    private function monthlyAttendance(Collection $records): array
    {
        return $records
            ->groupBy(fn ($record) => $record->checkin_at?->format('Y-m') ?? $record->created_at?->format('Y-m') ?? 'unknown')
            ->map(fn ($items, $month) => [
                'month' => $month,
                'rate' => round($items->whereIn('status', ['present', 'checked_in', 'late', 'excused'])->count() * 100 / max(1, $items->count()), 1),
                'present' => $items->whereIn('status', ['present', 'checked_in'])->count(),
                'late' => $items->where('status', 'late')->count(),
                'absent' => $items->where('status', 'absent')->count(),
            ])->values()->all();
    }

    private function itemsOf(Collection $items, array $types): array
    {
        return $items->filter(fn ($item) => in_array($item->item_type, $types, true))->values()->all();
    }

    private function skillScore(array $skills, array $categories): array
    {
        $matched = collect($skills)->filter(fn ($skill) => in_array($skill['category'] ?? '', $categories, true));
        return ['score' => round((float) $matched->avg('score'), 1), 'categories' => $categories];
    }

    private function growthTrend(float $score): array
    {
        return collect(range(5, 0))->map(fn ($month) => [
            'month' => now()->subMonths($month)->format('Y-m'),
            'score' => max(0, min(100, $score - ($month * 4))),
        ])->push(['month' => now()->format('Y-m'), 'score' => $score])->values()->all();
    }

    private function credentialTimeline(Collection $certificates, Collection $badges, Collection $portfolioItems): array
    {
        return collect()
            ->merge($certificates->map(fn (CertificateIssue $item) => ['type' => 'certificate', 'title' => $item->certificate_title, 'status' => $item->status, 'at' => $item->issued_at?->toDateString(), 'verify_qr' => $item->qr_payload]))
            ->merge($badges->map(fn (BadgeIssue $item) => ['type' => 'badge', 'title' => $item->badge?->name ?? 'Badge', 'status' => $item->status, 'at' => $item->issued_at?->toDateString(), 'verify_qr' => $item->verification_url]))
            ->merge($portfolioItems->map(fn (PortfolioItem $item) => ['type' => $item->item_type, 'title' => $item->title, 'status' => $item->status, 'at' => $item->issued_at?->toDateString(), 'verify_qr' => $item->evidence_url]))
            ->sortByDesc('at')
            ->values()
            ->all();
    }

    private function studentEnrollments(int $tenantId, LmsUser $student): Collection
    {
        return Enrollment::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->with(['course.owner', 'course.category', 'course.academicUnit', 'classSection', 'cohort'])
            ->latest('updated_at')
            ->get();
    }

    private function courseCard(Enrollment $enrollment, ?UserCourseProgress $progress): ?array
    {
        $course = $enrollment->course;
        if (! $course) {
            return null;
        }

        $lastComponent = $progress?->last_component_id
            ? CourseComponent::query()->whereKey($progress->last_component_id)->first()
            : null;
        $nextComponent = $lastComponent ?: CourseComponent::query()
            ->where('tenant_id', $course->tenant_id)
            ->where('course_id', $course->id)
            ->orderBy('sort_order')
            ->first();
        $progressPercent = (float) ($progress?->progress_percent ?? $enrollment->completion_percent ?? 0);
        $status = $progress?->status ?: $enrollment->status;

        return [
            'id' => $course->id,
            'course_id' => $course->id,
            'enrollment_id' => $enrollment->id,
            'thumbnail' => $course->thumbnail_url ?: '/demo-media/course-default.jpg',
            'title' => $course->title,
            'code' => $course->code,
            'teacher' => $course->owner?->full_name ?? 'Giảng viên phụ trách',
            'category' => $course->category?->name ?? data_get($course, 'settings.category') ?? $course->level,
            'faculty' => $course->academicUnit?->name ?? 'Khoa/Bộ môn',
            'semester' => data_get($enrollment, 'metadata.semester') ?? data_get($enrollment, 'classSection.metadata.semester') ?? now()->format('Y'),
            'skill' => data_get($course, 'settings.skill') ?? data_get($course, 'settings.skills.0') ?? $course->course_type,
            'progress' => round($progressPercent, 1),
            'status' => $status,
            'next_lesson' => $nextComponent?->title ?? 'Bài học đầu tiên',
            'lesson_id' => $nextComponent?->id,
            'last_accessed_at' => $progress?->last_accessed_at?->toDateTimeString() ?? $enrollment->updated_at?->toDateTimeString(),
            'starts_at' => $enrollment->classSection?->starts_at?->toDateString(),
            'actions' => [
                'continue' => '/courses/learn?course_id='.$course->id,
                'preview' => '/student/journey?course_id='.$course->id,
                'bookmark' => true,
                'favorite' => true,
            ],
        ];
    }

    private function filterCourseCards(Collection $cards, array $filters): Collection
    {
        $q = trim((string) ($filters['q'] ?? $filters['course'] ?? ''));
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $cards = $cards->filter(fn ($card) => str_contains(mb_strtolower($card['title'].' '.$card['code'].' '.$card['teacher'].' '.$card['skill']), $needle));
        }

        foreach (['teacher', 'skill', 'category', 'faculty', 'semester'] as $key) {
            if (! empty($filters[$key])) {
                $needle = mb_strtolower((string) $filters[$key]);
                $cards = $cards->filter(fn ($card) => str_contains(mb_strtolower((string) ($card[$key] ?? '')), $needle));
            }
        }

        return $cards->values();
    }

    private function journeyType(CourseComponent $component): string
    {
        $type = strtolower($component->component_type);
        if (str_contains($type, 'video')) {
            return 'video';
        }
        if (str_contains($type, 'quiz') || $component->exam?->exam_type === 'quiz') {
            return 'quiz';
        }
        if (str_contains($type, 'assign')) {
            return 'assignment';
        }
        if (str_contains($type, 'forum') || str_contains($type, 'discussion')) {
            return 'forum';
        }

        return $type ?: 'lesson';
    }

    private function journeyIcon(CourseComponent $component): string
    {
        return match ($this->journeyType($component)) {
            'video' => 'play-circle',
            'quiz' => 'clipboard-check',
            'assignment' => 'file-text',
            'forum' => 'messages-square',
            default => 'book-open',
        };
    }

    private function componentHref(CourseComponent $component): string
    {
        if ($component->exam) {
            return '/exams/take?course_id='.$component->course_id.'&component_id='.$component->id;
        }
        if ($component->assignment) {
            return '/assignments/submission?assignment_id='.$component->assignment->id;
        }

        return '/courses/learn?course_id='.$component->course_id.'&component_id='.$component->id;
    }

    private function assignmentTasks(int $tenantId, LmsUser $student, Collection $courseIds): Collection
    {
        if ($courseIds->isEmpty()) {
            return collect();
        }

        $submissions = AssignmentSubmission::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->latest('updated_at')
            ->get()
            ->keyBy('assignment_id');

        return Assignment::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('course_id', $courseIds)
            ->whereIn('status', ['published', 'active', 'open'])
            ->with('course:id,code,title')
            ->latest('due_at')
            ->limit(50)
            ->get()
            ->map(function (Assignment $assignment) use ($submissions) {
                $submission = $submissions->get($assignment->id);
                $status = $submission?->status ?: 'todo';

                return $this->taskPayload(
                    id: 'assignment-'.$assignment->id,
                    type: 'assignment',
                    title: $assignment->title,
                    course: $assignment->course?->title,
                    deadline: $assignment->due_at,
                    status: $status,
                    href: '/assignments/submission?assignment_id='.$assignment->id,
                    action: in_array($status, ['draft'], true) ? 'Continue' : (in_array($status, ['submitted', 'graded', 'approved'], true) ? 'Open' : 'Submit')
                );
            });
    }

    private function examTasks(int $tenantId, LmsUser $student, Collection $courseIds): Collection
    {
        $attempts = ExamAttempt::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->latest('updated_at')
            ->get()
            ->groupBy('exam_id');

        $assigned = ExamEnrollment::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->with('exam.course:id,code,title')
            ->latest('available_until')
            ->limit(50)
            ->get();

        $fallback = $assigned->isEmpty() && $courseIds->isNotEmpty()
            ? Exam::query()->where('tenant_id', $tenantId)->whereIn('course_id', $courseIds)->whereIn('status', ['published', 'active'])->with('course:id,code,title')->limit(20)->get()
            : collect();

        return $assigned->map(function (ExamEnrollment $row) use ($attempts) {
            $exam = $row->exam;
            $lastAttempt = $exam ? $attempts->get($exam->id)?->first() : null;
            $status = $lastAttempt?->status ?: $row->status;
            $type = $exam?->exam_type === 'quiz' ? 'quiz' : 'exam';

            return $this->taskPayload(
                id: $type.'-'.$row->id,
                type: $type,
                title: $exam?->title ?? 'Bài kiểm tra',
                course: $exam?->course?->title,
                deadline: $row->available_until ?? $exam?->close_at,
                status: $status ?: 'assigned',
                href: '/exams/take?exam_id='.$exam?->id,
                action: $lastAttempt && $lastAttempt->status === 'in_progress' ? 'Continue' : 'Start'
            );
        })->merge($fallback->map(function (Exam $exam) use ($attempts) {
            $lastAttempt = $attempts->get($exam->id)?->first();
            $type = $exam->exam_type === 'quiz' ? 'quiz' : 'exam';

            return $this->taskPayload(
                id: $type.'-'.$exam->id,
                type: $type,
                title: $exam->title,
                course: $exam->course?->title,
                deadline: $exam->close_at,
                status: $lastAttempt?->status ?: 'todo',
                href: '/exams/take?exam_id='.$exam->id,
                action: $lastAttempt && $lastAttempt->status === 'in_progress' ? 'Continue' : 'Start'
            );
        }))->values();
    }

    private function liveSessionTasks(int $tenantId, Collection $courseIds): Collection
    {
        if ($courseIds->isEmpty()) {
            return collect();
        }

        return LiveSession::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('course_id', $courseIds)
            ->where('start_at', '>=', now()->subDay())
            ->with('attendanceSessions')
            ->orderBy('start_at')
            ->limit(20)
            ->get()
            ->map(fn (LiveSession $session) => $this->taskPayload(
                id: 'live-'.$session->id,
                type: 'live_session',
                title: $session->title,
                course: 'Live Session',
                deadline: $session->start_at,
                status: $session->status,
                href: '/attendance/checkin?live_session_id='.$session->id,
                action: 'Open'
            ));
    }

    private function surveyTasks(int $tenantId, Collection $courseIds): Collection
    {
        return SurveyCampaign::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'launched', 'open'])
            ->where(function ($query) use ($courseIds) {
                $query->whereNull('course_id');
                if ($courseIds->isNotEmpty()) {
                    $query->orWhereIn('course_id', $courseIds);
                }
            })
            ->orderBy('ends_at')
            ->limit(20)
            ->get()
            ->map(fn (SurveyCampaign $campaign) => $this->taskPayload(
                id: 'survey-'.$campaign->id,
                type: 'survey',
                title: $campaign->title,
                course: 'Survey',
                deadline: $campaign->ends_at,
                status: $campaign->status,
                href: '/surveys?campaign_id='.$campaign->id,
                action: 'Start'
            ));
    }

    private function taskPayload(string $id, string $type, string $title, ?string $course, mixed $deadline, string $status, string $href, string $action): array
    {
        $date = $deadline ? Carbon::parse($deadline) : null;

        return [
            'id' => $id,
            'type' => $type,
            'priority' => $this->taskPriority($date, $status),
            'deadline' => $date?->toDateTimeString(),
            'course' => $course ?: '-',
            'title' => $title,
            'status' => $status,
            'action' => $action,
            'href' => $href,
        ];
    }

    private function taskPriority(?Carbon $date, string $status): string
    {
        if (in_array($status, ['submitted', 'completed', 'done', 'graded', 'approved'], true)) {
            return 'low';
        }
        if ($date?->isPast()) {
            return 'critical';
        }
        if ($date?->lessThanOrEqualTo(now()->addDay())) {
            return 'high';
        }
        if ($date?->lessThanOrEqualTo(now()->addWeek())) {
            return 'medium';
        }

        return 'low';
    }

    private function taskDate(array $task): ?Carbon
    {
        return $task['deadline'] ? Carbon::parse($task['deadline']) : null;
    }

    private function riskIndicator(Collection $enrollments, float $attendanceRate): array
    {
        $risk = round((float) $enrollments->avg('risk_score'), 1);
        if ($attendanceRate > 0) {
            $risk = max($risk, max(0, 80 - $attendanceRate));
        }
        $level = match (true) {
            $risk >= 80 => 'critical',
            $risk >= 60 => 'high',
            $risk >= 35 => 'medium',
            default => 'low',
        };

        return ['level' => $level, 'score' => $risk];
    }

    private function attendanceStreak(int $tenantId, LmsUser $student): int
    {
        $days = AttendanceRecord::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->whereIn('status', ['present', 'checked_in', 'late'])
            ->whereNotNull('checkin_at')
            ->latest('checkin_at')
            ->limit(60)
            ->get()
            ->pluck('checkin_at')
            ->filter()
            ->map(fn ($date) => $date->toDateString())
            ->unique()
            ->values();

        $streak = 0;
        $cursor = now()->toDateString();
        foreach ($days as $day) {
            if ($day === $cursor) {
                $streak++;
                $cursor = Carbon::parse($cursor)->subDay()->toDateString();
            } elseif ($streak === 0 && $day === now()->subDay()->toDateString()) {
                $streak++;
                $cursor = Carbon::parse($day)->subDay()->toDateString();
            }
        }

        return $streak;
    }
}
