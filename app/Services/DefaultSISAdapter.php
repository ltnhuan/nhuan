<?php

namespace App\Services;

use App\Contracts\SISAdapterContract;
use App\Models\IntegrationSystem;
use Illuminate\Support\Facades\Http;

class DefaultSISAdapter implements SISAdapterContract
{
    public function pullStudents(IntegrationSystem $system): array { return $this->get($system, '/students'); }
    public function pullTeachers(IntegrationSystem $system): array { return $this->get($system, '/teachers'); }
    public function pullClasses(IntegrationSystem $system): array { return $this->get($system, '/classes'); }
    public function pullCourseSections(IntegrationSystem $system): array { return $this->get($system, '/course-sections'); }
    public function pullEnrollments(IntegrationSystem $system): array { return $this->get($system, '/enrollments'); }
    public function pushGrades(IntegrationSystem $system, array $grades): array { return $this->post($system, '/grades', ['grades' => $grades]); }
    public function pushAttendance(IntegrationSystem $system, array $attendance): array { return $this->post($system, '/attendance', ['attendance' => $attendance]); }
    public function pushProgress(IntegrationSystem $system, array $progress): array { return $this->post($system, '/progress', ['progress' => $progress]); }
    public function healthCheck(IntegrationSystem $system): array { return $system->settings['mock'] ?? false ? ['ok' => true, 'system' => $system->code] : $this->get($system, '/health'); }
    private function get(IntegrationSystem $system, string $path): array { return Http::timeout(5)->baseUrl(rtrim($system->base_url, '/'))->get($path)->throw()->json() ?? []; }
    private function post(IntegrationSystem $system, string $path, array $payload): array { return Http::timeout(5)->baseUrl(rtrim($system->base_url, '/'))->post($path, $payload)->throw()->json() ?? []; }
}
