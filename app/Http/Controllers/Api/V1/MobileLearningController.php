<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Assignment;
use App\Models\AttendanceSession;
use App\Models\BadgeIssue;
use App\Models\CertificateIssue;
use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\DigitalPortfolio;
use App\Models\LmsUser;
use App\Models\OfflineQueue;
use App\Models\UserCourseProgress;
use App\Services\MobileSyncEngineService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MobileLearningController extends Controller
{
    public function bootstrap(Request $request)
    {
        $tenant = $request->attributes->get('tenant');
        $user = $this->mobileUser($request);
        $courses = Course::query()
            ->where('tenant_id', $tenant?->id)
            ->with(['components' => fn ($query) => $query->orderBy('sort_order')->limit(6)])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $progress = UserCourseProgress::query()
            ->where('tenant_id', $tenant?->id)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('course_id');

        return [
            'platforms' => ['ios', 'android', 'pwa'],
            'sso' => [
                'enabled' => true,
                'providers' => ['demo_sso', 'saml2', 'oidc'],
                'mobile_callback' => '/mobile',
            ],
            'offline' => [
                'enabled' => true,
                'tables' => ['offline_queue', 'offline_progress', 'offline_quiz_answers'],
                'sync_engine' => ['upload_pending' => true, 'conflict_resolve' => ['server_wins', 'client_wins', 'manual_review']],
            ],
            'courses' => $courses->map(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
                'description' => $course->short_description,
                'thumbnail_url' => $course->thumbnail_url,
                'estimated_hours' => $course->estimated_hours,
                'progress_percent' => (float) ($progress[$course->id]?->progress_percent ?? 0),
                'offline_ready' => $course->components->isNotEmpty(),
                'download_size_mb' => 120 + ($course->components->count() * 18),
                'components' => $course->components->map(fn (CourseComponent $component) => [
                    'id' => $component->id,
                    'title' => $component->title,
                    'type' => $component->component_type,
                    'downloadable' => in_array($component->component_type, ['video', 'pdf', 'text', 'quiz'], true),
                ])->values(),
            ])->values(),
            'continue_learning' => $this->continueLearning($tenant?->id, $user->id),
            'notifications' => $this->notifications($tenant?->id),
            'attendance' => $this->attendance($tenant?->id),
            'ai_tutor' => [
                'enabled' => true,
                'endpoint' => '/api/v1/ai/ask',
                'offline_prompts' => ['Tóm tắt bài học', 'Giải thích khái niệm', 'Tạo flashcard ôn tập'],
            ],
            'portfolio' => DigitalPortfolio::query()->where('tenant_id', $tenant?->id)->where('user_id', $user->id)->latest()->limit(3)->get(),
            'credential_wallet' => [
                'certificates' => CertificateIssue::query()->where('tenant_id', $tenant?->id)->where('user_id', $user->id)->latest()->limit(5)->get(),
                'badges' => BadgeIssue::query()->where('tenant_id', $tenant?->id)->where('user_id', $user->id)->latest()->limit(5)->get(),
            ],
            'pending_sync_count' => OfflineQueue::query()->where('tenant_id', $tenant?->id)->where('user_id', $user->id)->whereIn('status', ['pending', 'failed', 'conflict'])->count(),
        ];
    }

    public function sync(Request $request, MobileSyncEngineService $sync)
    {
        $data = $request->validate([
            'device_id' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'array'],
            'items.*.client_uuid' => ['nullable', 'string', 'max:120'],
            'items.*.operation' => ['required', 'string', 'max:80'],
            'items.*.payload' => ['required', 'array'],
            'items.*.conflict_strategy' => ['nullable', 'string', 'max:40'],
        ]);

        $tenant = $request->attributes->get('tenant');
        $user = $this->mobileUser($request);

        return $sync->sync((int) $tenant?->id, $user->id, $data['items'], $data['device_id'] ?? null);
    }

    public function pushSubscription(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys' => ['nullable', 'array'],
            'topics' => ['nullable', 'array'],
        ]);

        return [
            'status' => 'registered',
            'topics' => $data['topics'] ?? ['assignment', 'quiz', 'attendance', 'certificate'],
        ];
    }

    private function continueLearning(?int $tenantId, int $userId): array
    {
        $rows = UserCourseProgress::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->latest('last_accessed_at')
            ->limit(5)
            ->get();

        if ($rows->isEmpty()) {
            return Course::query()->where('tenant_id', $tenantId)->limit(3)->get()->map(fn (Course $course) => [
                'course_id' => $course->id,
                'title' => $course->title,
                'progress_percent' => 0,
                'last_component_id' => null,
            ])->all();
        }

        return $rows->map(fn (UserCourseProgress $row) => [
            'course_id' => $row->course_id,
            'title' => $row->course?->title,
            'progress_percent' => (float) $row->progress_percent,
            'last_component_id' => $row->last_component_id,
        ])->all();
    }

    private function notifications(?int $tenantId): array
    {
        return [
            ['type' => 'assignment', 'title' => 'Bài tập mới', 'body' => Assignment::query()->where('tenant_id', $tenantId)->latest()->value('title') ?? 'Có bài tập cần nộp'],
            ['type' => 'quiz', 'title' => 'Bài kiểm tra sắp đến hạn', 'body' => 'Ôn tập và hoàn tất khi trực tuyến hoặc ngoại tuyến.'],
            ['type' => 'attendance', 'title' => 'Điểm danh hôm nay', 'body' => 'Hỗ trợ QR và OTP trên thiết bị di động.'],
            ['type' => 'certificate', 'title' => 'Ví chứng chỉ số', 'body' => 'Chứng chỉ mới sẽ xuất hiện trong ví.'],
        ];
    }

    private function attendance(?int $tenantId): array
    {
        $session = AttendanceSession::query()->where('tenant_id', $tenantId)->latest()->first();

        return [
            'qr_enabled' => true,
            'otp_enabled' => true,
            'active_session_id' => $session?->id,
            'status' => $session?->status ?? 'none',
        ];
    }

    private function mobileUser(Request $request): LmsUser
    {
        $tenant = $request->attributes->get('tenant');
        $user = $request->user();

        if ($user instanceof LmsUser) {
            return $user;
        }

        if ($request->header('X-Demo-User-Email')) {
            $demo = LmsUser::query()
                ->where('tenant_id', $tenant?->id)
                ->where('email', $request->header('X-Demo-User-Email'))
                ->first();

            if ($demo) {
                return $demo;
            }
        }

        return LmsUser::query()->where('tenant_id', $tenant?->id)->orderBy('id')->firstOrFail();
    }
}
