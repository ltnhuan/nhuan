<?php

namespace App\Services;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class MoodleParityService
{
    public function report(): array
    {
        $features = collect($this->features())->map(function (array $feature) {
            $routes = collect($feature['routes'] ?? []);
            $existingRoutes = $routes->filter(fn (string $route) => $this->routeExists($route))->values();

            $coverage = $routes->isEmpty()
                ? ($feature['status'] === 'covered' ? 100 : 0)
                : (int) round(($existingRoutes->count() / $routes->count()) * 100);

            return [
                ...$feature,
                'existing_routes' => $existingRoutes->all(),
                'missing_routes' => $routes->diff($existingRoutes)->values()->all(),
                'coverage_percent' => $coverage,
                'status' => $this->status($feature['status'], $coverage),
            ];
        });

        return [
            'source' => [
                'name' => 'Moodle functional parity map',
                'strategy' => 'Rebuild Moodle-equivalent behavior in EraLMS modules; do not vendor-copy GPL code.',
            ],
            'summary' => [
                'total' => $features->count(),
                'covered' => $features->where('status', 'covered')->count(),
                'partial' => $features->where('status', 'partial')->count(),
                'planned' => $features->where('status', 'planned')->count(),
                'average_coverage_percent' => (int) round($features->avg('coverage_percent') ?? 0),
            ],
            'features' => $features->values()->all(),
        ];
    }

    public function syncPlan(): array
    {
        $report = $this->report();

        return [
            'updated_existing' => collect($report['features'])
                ->whereIn('status', ['covered', 'partial'])
                ->pluck('eralms_module')
                ->unique()
                ->values()
                ->all(),
            'planned_modules' => collect($report['features'])
                ->where('status', 'planned')
                ->pluck('eralms_module')
                ->unique()
                ->values()
                ->all(),
            'next_actions' => [
                'Run action registry sync so overlapping actions update in place.',
                'Expose missing module menus through config/eralms.php.',
                'Implement planned modules behind stable API paths before adding custom UI.',
            ],
        ];
    }

    private function status(string $declared, int $coverage): string
    {
        if ($declared === 'planned') {
            return 'planned';
        }

        return $coverage >= 80 ? 'covered' : 'partial';
    }

    private function routeExists(string $route): bool
    {
        [$method, $uri] = explode(' ', $route, 2);
        $uri = ltrim($uri, '/');

        foreach (Route::getRoutes() as $registeredRoute) {
            if (in_array($method, $registeredRoute->methods(), true) && $registeredRoute->uri() === $uri) {
                return true;
            }
        }

        return false;
    }

    private function features(): array
    {
        return [
            $this->feature('course_management', 'Course categories, sections, modules, clone, publish workflow', 'course', 'covered', [
                'GET api/v1/courses',
                'POST api/v1/courses',
                'POST api/v1/courses/{course}/clone',
                'POST api/v1/courses/{course}/publish',
                'POST api/v1/course-components',
            ]),
            $this->feature('activity_completion', 'Completion tracking, access checks, manual approval, progress summary', 'learning_path', 'covered', [
                'POST api/v1/components/{component}/complete',
                'GET api/v1/components/{component}/access-check',
                'POST api/v1/completions/{completion}/approve',
            ]),
            $this->feature('question_bank_quiz', 'Question categories, versions, import, blueprints, attempts and grading', 'question_bank', 'covered', [
                'GET api/v1/question-banks',
                'POST api/v1/questions',
                'POST api/v1/question-imports',
                'POST api/v1/exams/{exam}/attempts/start',
            ]),
            $this->feature('gradebook', 'Grade items, calculation, approvals, lock and SIS push', 'gradebook', 'covered', [
                'GET api/v1/gradebooks',
                'POST api/v1/gradebooks/{gradebook}/recalculate',
                'POST api/v1/gradebooks/{gradebook}/approve',
                'POST api/v1/gradebooks/{gradebook}/sync-to-sis',
            ]),
            $this->feature('assignments', 'Assignment publish, submit, upload, grade, return and approval', 'assignment', 'covered', [
                'GET api/v1/assignments',
                'POST api/v1/assignments',
                'POST api/v1/assignments/{assignment}/submit',
                'POST api/v1/assignment-submissions/{submission}/grade',
            ]),
            $this->feature('enrolments', 'Cohorts, class sections, self enrolment, bulk enrolment and imports', 'enrollment', 'covered', [
                'GET api/v1/enrollment/cohorts',
                'POST api/v1/enrollment/sections/{section}/self-enroll',
                'POST api/v1/enrollment/records/bulk',
                'POST api/v1/enrollment/imports',
            ]),
            $this->feature('forums_wiki_blog', 'Forums, discussions, reactions, wikis, blogs, moderation and reputation', 'community', 'covered', [
                'GET api/v1/community/forums',
                'POST api/v1/community/forums/{forum}/threads',
                'POST api/v1/community/wikis',
                'POST api/v1/community/blogs',
            ]),
            $this->feature('scorm_lti_xapi', 'SCORM packages, xAPI statements, LTI registrations and external tools', 'standards', 'covered', [
                'GET api/v1/learning-standards/scorm/packages',
                'POST api/v1/learning-standards/xapi/statements',
                'GET api/v1/learning-standards/lti/registrations',
                'GET api/v1/learning-standards/external-tools',
            ]),
            $this->feature('surveys_feedback', 'Survey forms, campaigns, responses, export and improvement evidence', 'survey', 'covered', [
                'GET api/v1/surveys/forms',
                'POST api/v1/surveys/campaigns/{campaign}/responses',
                'GET api/v1/surveys/campaigns/{campaign}/export',
            ]),
            $this->feature('competency_outcomes', 'Outcome frameworks, mappings, achievement dashboard and accreditation reports', 'obe', 'covered', [
                'GET api/v1/obe/outcomes',
                'POST api/v1/obe/outcome-mappings',
                'GET api/v1/obe/outcome-matrix',
                'GET api/v1/obe/accreditation-reports',
            ]),
            $this->feature('badges_certificates', 'Badges, certificates, wallet, verification and auto issue rules', 'credential', 'covered', [
                'GET api/v1/credentials/certificates',
                'POST api/v1/credentials/certificates/issue',
                'GET api/v1/credentials/wallet',
            ]),
            $this->feature('plugins', 'Plugin inventory, connector state and extension governance', 'plugins', 'partial', [
                'GET api/v1/admin/lms/moodle-parity',
            ]),
            $this->feature('backup_restore', 'Course backup, restore point, dry-run restore and audit', 'backup', 'planned', []),
            $this->feature('security_privacy', 'Session policy, account lock, capability audit and privacy export', 'security', 'partial', [
                'GET api/v1/core/audit-logs',
                'POST api/v1/admin/lms/action-check/smoke-test',
            ]),
        ];
    }

    private function feature(string $key, string $label, string $module, string $status, array $routes): array
    {
        return [
            'moodle_feature' => Str::headline($key),
            'feature_key' => $key,
            'label' => $label,
            'eralms_module' => $module,
            'status' => $status,
            'routes' => $routes,
        ];
    }
}
