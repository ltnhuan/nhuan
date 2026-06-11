<?php

namespace App\Services;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class IntegrationApiCatalogService
{
    public function catalog(): array
    {
        $routes = $this->apiRoutes();

        return [
            'version' => 'v1',
            'base_path' => '/api/v1',
            'standards' => $this->standards(),
            'modules' => $this->modules($routes),
            'events' => $this->events(),
        ];
    }

    public function openApi(): array
    {
        $paths = [];

        foreach ($this->apiRoutes() as $route) {
            $uri = '/'.$route->uri();
            $path = preg_replace('/\{([^}]+)\}/', '{$1}', $uri);

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $paths[$path][strtolower($method)] = [
                    'tags' => [$this->moduleName($route->uri())],
                    'summary' => $this->summary($route),
                    'operationId' => $this->operationId($method, $route->uri()),
                    'parameters' => $this->parameters($route->uri()),
                    'responses' => [
                        '200' => ['$ref' => '#/components/responses/Success'],
                        '201' => ['$ref' => '#/components/responses/Created'],
                        '401' => ['$ref' => '#/components/responses/Unauthorized'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                        '422' => ['$ref' => '#/components/responses/ValidationError'],
                    ],
                ];
            }
        }

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'EraLMS Integration API',
                'version' => '1.0.0',
                'description' => 'API đấu nối hệ thống EraLMS cho SIS, HRM, SSO, cổng học liệu, mobile app, BI và webhook.',
            ],
            'servers' => [['url' => '/api/v1']],
            'security' => [['ApiKeyAuth' => []], ['BearerAuth' => []]],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-API-Key'],
                    'BearerAuth' => ['type' => 'http', 'scheme' => 'bearer'],
                ],
                'responses' => [
                    'Success' => ['description' => 'Thành công'],
                    'Created' => ['description' => 'Đã tạo dữ liệu'],
                    'Unauthorized' => ['description' => 'Chưa xác thực'],
                    'Forbidden' => ['description' => 'Không đủ quyền'],
                    'ValidationError' => ['description' => 'Dữ liệu không hợp lệ'],
                ],
            ],
        ];
    }

    private function apiRoutes(): Collection
    {
        return collect(Route::getRoutes())
            ->filter(fn (LaravelRoute $route) => Str::startsWith($route->uri(), 'api/v1/'))
            ->reject(fn (LaravelRoute $route) => $route->uri() === 'api/v1/health')
            ->sortBy(fn (LaravelRoute $route) => $route->uri())
            ->values();
    }

    private function modules(Collection $routes): array
    {
        return $routes
            ->groupBy(fn (LaravelRoute $route) => $this->moduleName($route->uri()))
            ->map(fn (Collection $moduleRoutes, string $module) => [
                'name' => $module,
                'description' => $this->moduleDescription($module),
                'endpoint_count' => $moduleRoutes->sum(fn (LaravelRoute $route) => count(array_diff($route->methods(), ['HEAD']))),
                'endpoints' => $moduleRoutes->flatMap(fn (LaravelRoute $route) => collect(array_diff($route->methods(), ['HEAD']))->map(fn (string $method) => [
                    'method' => $method,
                    'path' => '/'.$route->uri(),
                    'action' => $this->summary($route),
                    'middleware' => array_values(array_filter($route->middleware(), fn (string $middleware) => ! in_array($middleware, ['api', 'tenant.resolve', 'api.performance'], true))),
                ]))->values()->all(),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    private function standards(): array
    {
        return [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Tenant-Code' => 'Mã tenant khi đấu nối multi-tenant',
                'X-API-Key' => 'API key cho hệ thống ngoài hoặc Authorization: Bearer cho người dùng',
                'Idempotency-Key' => 'Bắt buộc với POST/PATCH đồng bộ để chống ghi trùng',
                'X-Request-Id' => 'Mã trace xuyên hệ thống',
            ],
            'response_success' => ['success' => true, 'message' => '...', 'data' => [], 'meta' => []],
            'response_error' => ['success' => false, 'message' => '...', 'errors' => [], 'code' => 'VALIDATION_FAILED', 'meta' => []],
            'pagination' => ['page', 'per_page', 'total', 'last_page'],
        ];
    }

    private function events(): array
    {
        return [
            'inbound' => ['sis.student.updated', 'sis.class.updated', 'sis.enrollment.updated', 'hrm.teacher.updated'],
            'outbound' => ['lms.grade.synced', 'lms.attendance.synced', 'lms.progress.updated', 'credential.issued', 'certificate.revoked'],
            'signature' => 'HMAC-SHA256 trên raw body, gửi qua header X-ERALMS-Signature.',
        ];
    }

    private function moduleName(string $uri): string
    {
        $path = Str::after($uri, 'api/v1/');
        $segment = Str::before($path, '/');

        return match ($segment) {
            'core' => 'Core IAM',
            'courses', 'course-categories', 'course-sections', 'course-components', 'activity-types' => 'Course Studio',
            'repository', 'editor' => 'Content Repository',
            'question-banks', 'question-categories', 'questions', 'question-imports', 'exam-blueprints', 'learning-outcomes', 'question-coverage' => 'Question Bank',
            'exams', 'my-exams', 'exam-attempts' => 'Online Exam',
            'assignments' => 'Assignment',
            'gradebooks', 'grade-items', 'grades' => 'Gradebook',
            'credentials' => 'Digital Credential',
            'live-sessions', 'attendance-sessions', 'attendance' => 'Attendance',
            'integrations' => 'Integration Hub',
            'learning-standards' => 'Learning Standards',
            'obe' => 'OBE Accreditation',
            'enrollment' => 'Enrollment',
            'analytics' => 'Phân tích học tập',
            'surveys' => 'Survey Evaluation',
            'community' => 'Learning Community',
            'ai' => 'AI Learning',
            'mobile' => 'Mobile Learning',
            default => Str::headline($segment),
        };
    }

    private function moduleDescription(string $module): string
    {
        return match ($module) {
            'Core IAM' => 'Tenant, người dùng, vai trò, phân quyền, cấu hình và audit log.',
            'Course Studio' => 'Thiết kế khóa học, cấu trúc section/component, review, publish và học liệu gắn khóa.',
            'Integration Hub' => 'Kết nối SIS/HRM/BI, mapping định danh, webhook, sync job và event log.',
            default => 'Nhóm API nghiệp vụ của module '.$module.'.',
        };
    }

    private function summary(LaravelRoute $route): string
    {
        $action = $route->getActionName();

        return Str::contains($action, '@') ? Str::after($action, '@') : 'call';
    }

    private function operationId(string $method, string $uri): string
    {
        return Str::camel(strtolower($method).' '.str_replace(['/', '{', '}'], ' ', Str::after($uri, 'api/v1/')));
    }

    private function parameters(string $uri): array
    {
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);

        return collect($matches[1] ?? [])->map(fn (string $name) => [
            'name' => $name,
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'string'],
        ])->all();
    }
}
