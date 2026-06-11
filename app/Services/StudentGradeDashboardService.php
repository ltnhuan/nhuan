<?php

namespace App\Services;

use App\Models\Course;
use App\Models\GradeSummary;
use App\Models\Gradebook;
use App\Models\LearnerGrade;
use App\Models\LmsUser;
use Illuminate\Support\Collection;

class StudentGradeDashboardService
{
    public function dashboard(int $tenantId, LmsUser $student): array
    {
        $startedAt = microtime(true);

        $summaries = GradeSummary::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->latest('updated_at')
            ->get();

        $gradebookIds = $summaries->pluck('gradebook_id')->filter()->unique()->values();
        $gradebooks = Gradebook::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $gradebookIds)
            ->with([
                'course:id,code,title,academic_unit_id,thumbnail_url,estimated_hours,settings',
                'items' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->get()
            ->keyBy('id');

        $grades = LearnerGrade::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->whereIn('gradebook_id', $gradebookIds)
            ->with('item')
            ->get()
            ->groupBy('gradebook_id');

        $courseGrades = $summaries
            ->map(fn (GradeSummary $summary) => $this->courseGrade($summary, $gradebooks->get($summary->gradebook_id), $grades->get($summary->gradebook_id, collect())))
            ->filter()
            ->values();

        $programGpa = $this->weightedGpa($courseGrades);
        $semesterCourses = $courseGrades->take(5);
        $semesterGpa = $this->weightedGpa($semesterCourses);
        $currentCourse = $courseGrades->first();
        $currentGpa = $currentCourse ? (float) $currentCourse['grade_points'] : $programGpa;
        $forecast = $this->forecast($courseGrades, $programGpa);
        $distribution = $this->distribution($courseGrades);
        $comparison = $this->comparison($tenantId, $gradebookIds, $courseGrades, $programGpa);
        $analytics = $this->analytics($tenantId, $gradebookIds, $courseGrades);

        return [
            'student' => [
                'id' => $student->id,
                'code' => $student->code,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'avatar_url' => $student->avatar_url,
            ],
            'context' => [
                'current_semester' => $currentCourse['semester'] ?? $this->defaultSemester(),
                'program' => $student->metadata['program'] ?? $student->metadata['major'] ?? 'Chương trình đào tạo',
                'courses_count' => $courseGrades->count(),
                'credits_completed' => $courseGrades->where('pass_status', 'passed')->sum('credits'),
                'credits_attempted' => $courseGrades->sum('credits'),
            ],
            'metrics' => [
                'current_gpa' => $currentGpa,
                'semester_gpa' => $semesterGpa,
                'program_gpa' => $programGpa,
                'expected_gpa' => $forecast['expected_gpa'],
                'graduation_gpa' => $forecast['graduation_gpa'],
            ],
            'grade_trend' => $this->trend($courseGrades),
            'course_grades' => $courseGrades,
            'grade_distribution' => $distribution,
            'comparison' => $comparison,
            'analytics' => $analytics,
            'charts' => [
                'line' => $this->trend($courseGrades),
                'bar' => $courseGrades->map(fn ($course) => [
                    'label' => $course['course_code'],
                    'value' => $course['percent'],
                    'letter_grade' => $course['letter_grade'],
                ])->values(),
                'radar' => $this->radar($courseGrades),
                'distribution' => $distribution,
            ],
            'drilldown' => [
                'courses' => $courseGrades,
                'quiz' => $this->itemsByGroup($courseGrades, 'quiz'),
                'assignment' => $this->itemsByGroup($courseGrades, 'assignment'),
                'exam' => $this->itemsByGroup($courseGrades, 'exam'),
            ],
            'forecast' => $forecast,
            'performance' => [
                'target_ms' => 1000,
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ],
        ];
    }

    private function courseGrade(GradeSummary $summary, ?Gradebook $gradebook, Collection $grades): ?array
    {
        if (! $gradebook) {
            return null;
        }

        $course = $gradebook->course;
        $percent = round((float) $summary->percent, 1);
        $letter = $summary->letter_grade ?: $this->letterGrade($percent);
        $gradePoints = $this->gradePoints($percent);
        $passStatus = $this->passStatus($summary->pass_status, $percent);
        $itemsById = $gradebook->items->keyBy('id');
        $items = $grades
            ->map(fn (LearnerGrade $grade) => $this->itemGrade($grade, $itemsById->get($grade->grade_item_id), $gradebook->course_id))
            ->filter()
            ->values();

        return [
            'gradebook_id' => $gradebook->id,
            'course_id' => $gradebook->course_id,
            'course_code' => $course?->code ?? 'GB-'.$gradebook->id,
            'course_title' => $course?->title ?? $gradebook->title,
            'gradebook_title' => $gradebook->title,
            'semester' => $gradebook->settings['semester'] ?? $course?->settings['semester'] ?? $this->defaultSemester(),
            'credits' => $this->credits($course),
            'percent' => $percent,
            'total_score' => round((float) $summary->total_score, 1),
            'max_score' => round((float) $summary->max_score, 1),
            'letter_grade' => $letter,
            'grade_points' => $gradePoints,
            'pass_status' => $passStatus,
            'status' => $summary->status,
            'retake_risk' => $this->retakeRisk($percent, $passStatus, $items),
            'updated_at' => optional($summary->updated_at)->toISOString(),
            'href' => '/courses/learn?course_id='.$gradebook->course_id,
            'journey_href' => '/student/journey?course_id='.$gradebook->course_id,
            'items' => $items,
        ];
    }

    private function itemGrade(LearnerGrade $grade, $item, ?int $courseId = null): ?array
    {
        if (! $item && ! $grade->item) {
            return null;
        }

        $item ??= $grade->item;
        $score = $grade->final_score ?? $grade->raw_score;
        $maxScore = max((float) ($item?->max_score ?? 10), 1);
        $percent = $score === null ? null : round((float) $score * 100 / $maxScore, 1);
        $type = $this->itemGroup($item?->source_type, $item?->title);

        return [
            'id' => $grade->id,
            'grade_item_id' => $grade->grade_item_id,
            'title' => $item?->title ?? 'Grade item',
            'type' => $type,
            'source_type' => $item?->source_type,
            'source_id' => $item?->source_id,
            'course_id' => $courseId,
            'score' => $score === null ? null : round((float) $score, 2),
            'max_score' => round($maxScore, 2),
            'percent' => $percent,
            'weight' => $item?->weight === null ? null : round((float) $item->weight, 1),
            'status' => $grade->source_status,
            'feedback' => $grade->feedback,
            'href' => $this->itemHref($type, $item?->source_id, $courseId, $item?->id),
            'result_href' => $type === 'exam' || $type === 'quiz' ? '/exams/results'.($courseId ? '?course_id='.$courseId : '') : null,
        ];
    }

    private function comparison(int $tenantId, Collection $gradebookIds, Collection $courseGrades, float $studentGpa): array
    {
        $studentPercent = round((float) $courseGrades->avg('percent'), 1);
        $courseIds = $courseGrades->pluck('course_id')->filter()->unique()->values();
        $unitIds = Course::query()->whereIn('id', $courseIds)->pluck('academic_unit_id')->filter()->unique()->values();
        $facultyGradebookIds = $unitIds->isEmpty()
            ? collect()
            : Gradebook::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('course_id', Course::query()->whereIn('academic_unit_id', $unitIds)->select('id'))
                ->pluck('id');

        $classAverage = $this->avgPercent($tenantId, $gradebookIds);
        $facultyAverage = $facultyGradebookIds->isEmpty() ? $classAverage : $this->avgPercent($tenantId, $facultyGradebookIds);
        $programAverage = round((float) GradeSummary::query()->where('tenant_id', $tenantId)->avg('percent'), 1);

        return [
            $this->comparisonRow('Class', $studentPercent, $classAverage),
            $this->comparisonRow('Faculty', $studentPercent, $facultyAverage),
            $this->comparisonRow('Program', round($studentGpa * 25, 1), $programAverage),
        ];
    }

    private function analytics(int $tenantId, Collection $gradebookIds, Collection $courseGrades): array
    {
        $total = max($courseGrades->count(), 1);
        $passed = $courseGrades->where('pass_status', 'passed')->count();
        $failed = $courseGrades->where('pass_status', 'failed')->count();
        $retakeCourses = $courseGrades
            ->filter(fn ($course) => in_array($course['retake_risk'], ['medium', 'high'], true))
            ->values();
        $peerTotal = GradeSummary::query()->where('tenant_id', $tenantId)->whereIn('gradebook_id', $gradebookIds)->count();
        $peerPassed = GradeSummary::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('gradebook_id', $gradebookIds)
            ->where(function ($query) {
                $query->where('pass_status', 'passed')->orWhere('percent', '>=', 50);
            })
            ->count();

        return [
            'pass_rate' => round($passed * 100 / $total, 1),
            'fail_rate' => round($failed * 100 / $total, 1),
            'peer_pass_rate' => $peerTotal ? round($peerPassed * 100 / $peerTotal, 1) : 0,
            'retake_risk' => round($retakeCourses->count() * 100 / $total, 1),
            'retake_courses' => $retakeCourses->map(fn ($course) => [
                'course_code' => $course['course_code'],
                'course_title' => $course['course_title'],
                'percent' => $course['percent'],
                'risk' => $course['retake_risk'],
            ])->values(),
        ];
    }

    private function forecast(Collection $courseGrades, float $programGpa): array
    {
        $ordered = $courseGrades->sortBy('updated_at')->values();
        $recent = $ordered->slice(-3)->avg('grade_points') ?: $programGpa;
        $previous = $ordered->slice(max(0, $ordered->count() - 6), 3)->avg('grade_points') ?: $recent;
        $delta = round($recent - $previous, 2);
        $expected = $this->boundGpa($programGpa + ($delta * 0.35));
        $graduation = $this->boundGpa(($programGpa * 0.65) + ($expected * 0.35));

        return [
            'expected_gpa' => $expected,
            'graduation_gpa' => $graduation,
            'trend_delta' => $delta,
            'confidence' => $courseGrades->count() >= 6 ? 'high' : ($courseGrades->count() >= 3 ? 'medium' : 'low'),
            'recommendations' => $this->forecastRecommendations($courseGrades, $expected),
        ];
    }

    private function trend(Collection $courseGrades): Collection
    {
        return $courseGrades
            ->sortBy('updated_at')
            ->values()
            ->map(fn ($course, int $index) => [
                'label' => $course['course_code'] ?: 'Course '.($index + 1),
                'course_title' => $course['course_title'],
                'percent' => $course['percent'],
                'gpa' => $course['grade_points'],
                'semester' => $course['semester'],
            ]);
    }

    private function radar(Collection $courseGrades): Collection
    {
        $items = $courseGrades->flatMap(fn ($course) => $course['items']);
        $groups = [
            'quiz' => 'Quiz',
            'assignment' => 'Assignment',
            'exam' => 'Exam',
            'attendance' => 'Attendance',
            'coursework' => 'Coursework',
        ];

        return collect($groups)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'value' => round((float) $items->where('type', $key)->avg('percent'), 1),
        ])->values();
    }

    private function distribution(Collection $courseGrades): Collection
    {
        $letters = ['A', 'B', 'C', 'D', 'F'];

        return collect($letters)->map(fn ($letter) => [
            'label' => $letter,
            'value' => $courseGrades->filter(fn ($course) => str_starts_with((string) $course['letter_grade'], $letter))->count(),
        ])->values();
    }

    private function itemsByGroup(Collection $courseGrades, string $group): Collection
    {
        return $courseGrades
            ->flatMap(fn ($course) => collect($course['items'])->where('type', $group)->map(fn ($item) => $item + [
                'course_id' => $course['course_id'],
                'course_code' => $course['course_code'],
                'course_title' => $course['course_title'],
                'course_href' => $course['href'],
                'journey_href' => $course['journey_href'],
            ]))
            ->values();
    }

    private function itemHref(string $type, ?int $sourceId, ?int $courseId, ?int $gradeItemId): string
    {
        $params = [];
        if ($courseId) {
            $params['course_id'] = $courseId;
        }
        if ($gradeItemId) {
            $params['grade_item_id'] = $gradeItemId;
        }

        if ($type === 'quiz' || $type === 'exam') {
            if ($sourceId) {
                $params['exam_id'] = $sourceId;

                return '/exams/take'.($params ? '?'.http_build_query($params) : '');
            }

            return $courseId ? '/courses/learn?course_id='.$courseId : '/exams';
        }

        if ($type === 'assignment') {
            if ($sourceId) {
                $params['assignment_id'] = $sourceId;

                return '/assignments/submission'.($params ? '?'.http_build_query($params) : '');
            }

            return $courseId ? '/courses/learn?course_id='.$courseId : '/assignments/submission';
        }

        if ($courseId) {
            return '/courses/learn?course_id='.$courseId;
        }

        return '/courses';
    }

    private function forecastRecommendations(Collection $courseGrades, float $expectedGpa): array
    {
        $weakCourse = $courseGrades->sortBy('percent')->first();
        $weakItem = $courseGrades
            ->flatMap(fn ($course) => collect($course['items'])->map(fn ($item) => $item + ['course_code' => $course['course_code']]))
            ->filter(fn ($item) => $item['percent'] !== null)
            ->sortBy('percent')
            ->first();

        return array_values(array_filter([
            $weakCourse ? 'Ưu tiên ôn tập '.$weakCourse['course_code'].' vì điểm tổng kết đang thấp nhất.' : null,
            $weakItem ? 'Cải thiện nhóm '.$weakItem['type'].' của '.$weakItem['course_code'].' trước đợt đánh giá tiếp theo.' : null,
            $expectedGpa < 2.0 ? 'Cần gặp cố vấn học tập để lập kế hoạch học lại/thi lại.' : 'Duy trì tiến độ hiện tại và tập trung các học phần có trọng số cao.',
        ]));
    }

    private function comparisonRow(string $scope, float $student, float $average): array
    {
        return [
            'scope' => $scope,
            'student' => round($student, 1),
            'average' => round($average, 1),
            'delta' => round($student - $average, 1),
        ];
    }

    private function avgPercent(int $tenantId, Collection $gradebookIds): float
    {
        if ($gradebookIds->isEmpty()) {
            return 0;
        }

        return round((float) GradeSummary::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('gradebook_id', $gradebookIds)
            ->avg('percent'), 1);
    }

    private function weightedGpa(Collection $courseGrades): float
    {
        $credits = max((float) $courseGrades->sum('credits'), 1);
        $points = $courseGrades->sum(fn ($course) => (float) $course['grade_points'] * (float) $course['credits']);

        return round($points / $credits, 2);
    }

    private function credits(?Course $course): float
    {
        return (float) ($course?->settings['credits'] ?? $course?->settings['credit'] ?? 3);
    }

    private function gradePoints(float $percent): float
    {
        return match (true) {
            $percent >= 90 => 4.0,
            $percent >= 85 => 3.7,
            $percent >= 80 => 3.5,
            $percent >= 75 => 3.0,
            $percent >= 70 => 2.5,
            $percent >= 65 => 2.0,
            $percent >= 60 => 1.5,
            $percent >= 50 => 1.0,
            default => 0.0,
        };
    }

    private function letterGrade(float $percent): string
    {
        return match (true) {
            $percent >= 90 => 'A',
            $percent >= 80 => 'B+',
            $percent >= 70 => 'B',
            $percent >= 60 => 'C',
            $percent >= 50 => 'D',
            default => 'F',
        };
    }

    private function passStatus(?string $status, float $percent): string
    {
        if (in_array($status, ['passed', 'failed'], true)) {
            return $status;
        }

        return $percent >= 50 ? 'passed' : 'failed';
    }

    private function retakeRisk(float $percent, string $passStatus, Collection $items): string
    {
        $minItemPercent = $items->filter(fn ($item) => $item['percent'] !== null)->min('percent');

        if ($passStatus === 'failed' || $percent < 50) {
            return 'high';
        }

        if ($percent < 60 || ($minItemPercent !== null && $minItemPercent < 50)) {
            return 'medium';
        }

        return 'low';
    }

    private function itemGroup(?string $sourceType, ?string $title): string
    {
        $needle = strtolower(trim(($sourceType ?? '').' '.($title ?? '')));

        return match (true) {
            str_contains($needle, 'quiz') => 'quiz',
            str_contains($needle, 'assignment') || str_contains($needle, 'bai tap') => 'assignment',
            str_contains($needle, 'exam') || str_contains($needle, 'final') || str_contains($needle, 'midterm') => 'exam',
            str_contains($needle, 'attendance') => 'attendance',
            default => 'coursework',
        };
    }

    private function boundGpa(float $gpa): float
    {
        return round(min(4, max(0, $gpa)), 2);
    }

    private function defaultSemester(): string
    {
        return 'HK 2 2025-2026';
    }
}
