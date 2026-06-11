<?php

namespace App\Services;

use App\Models\ExternalTool;
use App\Models\LmsUser;
use App\Models\LtiLaunch;
use App\Models\LtiRegistration;
use App\Models\ScormAttempt;
use App\Models\ScormEvent;
use App\Models\ScormPackage;
use App\Models\XapiStatement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LearningStandardsService
{
    public function uploadScormPackage(int $tenantId, UploadedFile $file, array $data, ?int $userId = null): ScormPackage
    {
        $standard = $data['standard'] ?? 'scorm_1_2';
        $path = $file->store("tenants/{$tenantId}/scorm", 'local');

        return ScormPackage::query()->create([
            'tenant_id' => $tenantId,
            'course_id' => $data['course_id'] ?? null,
            'title' => $data['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'standard' => $standard,
            'version' => $standard === 'scorm_2004' ? '2004 4th Edition' : '1.2',
            'launch_path' => $data['launch_path'] ?? 'index.html',
            'zip_path' => $path,
            'file_size' => $file->getSize() ?: Storage::disk('local')->size($path),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'status' => 'ready',
            'manifest' => [
                'source_file' => $file->getClientOriginalName(),
                'standard' => $standard,
                'launch_path' => $data['launch_path'] ?? 'index.html',
            ],
            'metadata' => $data['metadata'] ?? [],
            'uploaded_by' => $userId,
        ]);
    }

    public function launchScorm(ScormPackage $package, int $userId, array $runtime = []): array
    {
        $attempt = ScormAttempt::query()->create([
            'tenant_id' => $package->tenant_id,
            'package_id' => $package->id,
            'user_id' => $userId,
            'standard' => $package->standard,
            'status' => 'launched',
            'completion_status' => 'incomplete',
            'runtime_data' => $runtime,
            'started_at' => now(),
        ]);

        ScormEvent::query()->create([
            'tenant_id' => $package->tenant_id,
            'attempt_id' => $attempt->id,
            'package_id' => $package->id,
            'event_type' => 'launch',
            'payload' => ['runtime' => $runtime],
            'occurred_at' => now(),
        ]);

        return [
            'attempt' => $attempt,
            'launch_url' => "/scorm/packages/{$package->id}/{$package->launch_path}",
        ];
    }

    public function trackScorm(ScormAttempt $attempt, array $data): ScormAttempt
    {
        $completion = $data['completion_status'] ?? $attempt->completion_status;
        $progress = isset($data['progress']) ? min(100, max(0, (float) $data['progress'])) : (float) $attempt->progress;
        $score = array_key_exists('score', $data) ? $data['score'] : $attempt->score;
        $isComplete = in_array($completion, ['completed', 'passed'], true) || $progress >= 100;

        $attempt->forceFill([
            'status' => $isComplete ? 'completed' : 'in_progress',
            'progress' => $progress,
            'score' => $score,
            'completion_status' => $isComplete ? 'completed' : $completion,
            'success_status' => $data['success_status'] ?? $attempt->success_status,
            'session_time_seconds' => $data['session_time_seconds'] ?? $attempt->session_time_seconds,
            'runtime_data' => array_replace($attempt->runtime_data ?? [], $data['runtime_data'] ?? []),
            'completed_at' => $isComplete ? ($attempt->completed_at ?: now()) : null,
        ])->save();

        ScormEvent::query()->create([
            'tenant_id' => $attempt->tenant_id,
            'attempt_id' => $attempt->id,
            'package_id' => $attempt->package_id,
            'event_type' => $data['event_type'] ?? 'runtime_commit',
            'progress' => $progress,
            'score' => $score,
            'completion_status' => $attempt->completion_status,
            'payload' => $data,
            'occurred_at' => now(),
        ]);

        return $attempt->fresh();
    }

    public function storeXapiStatement(int $tenantId, array $statement): XapiStatement
    {
        return XapiStatement::query()->create([
            'tenant_id' => $tenantId,
            'statement_uuid' => $statement['id'] ?? (string) Str::uuid(),
            'actor' => $statement['actor'],
            'verb' => $statement['verb'],
            'object' => $statement['object'],
            'result' => $statement['result'] ?? null,
            'context' => $statement['context'] ?? null,
            'raw_statement' => $statement,
            'stored_at' => now(),
            'timestamp' => $statement['timestamp'] ?? now(),
        ]);
    }

    public function registerLtiTool(int $tenantId, array $data): LtiRegistration
    {
        return LtiRegistration::query()->create($data + [
            'tenant_id' => $tenantId,
            'tool_type' => 'tool_provider',
            'status' => 'active',
            'scopes' => ['openid', 'profile', 'email', 'https://purl.imsglobal.org/spec/lti-ags/scope/score'],
            'settings' => [],
        ]);
    }

    public function launchLti(LtiRegistration $registration, array $data): array
    {
        $launch = LtiLaunch::query()->create([
            'tenant_id' => $registration->tenant_id,
            'registration_id' => $registration->id,
            'user_id' => $data['user_id'] ?? null,
            'resource_link_id' => $data['resource_link_id'] ?? 'resource-'.$registration->id,
            'target_link_uri' => $data['target_link_uri'] ?? $registration->launch_url,
            'roles' => $data['roles'] ?? ['Learner'],
            'claims' => [
                'iss' => $registration->issuer,
                'aud' => $registration->client_id,
                'deployment_id' => $registration->deployment_id,
                'message_type' => 'LtiResourceLinkRequest',
            ] + ($data['claims'] ?? []),
            'status' => 'launched',
            'launched_at' => now(),
        ]);

        return ['launch' => $launch, 'redirect_url' => $launch->target_link_uri, 'claims' => $launch->claims];
    }

    public function registerExternalTool(int $tenantId, array $data): ExternalTool
    {
        return ExternalTool::query()->create($data + ['tenant_id' => $tenantId, 'status' => 'active', 'capabilities' => [], 'settings' => []]);
    }

    public function analytics(int $tenantId): array
    {
        return [
            'scorm_usage' => [
                'packages' => ScormPackage::query()->where('tenant_id', $tenantId)->count(),
                'attempts' => ScormAttempt::query()->where('tenant_id', $tenantId)->count(),
                'completed_attempts' => ScormAttempt::query()->where('tenant_id', $tenantId)->where('completion_status', 'completed')->count(),
                'average_score' => round((float) ScormAttempt::query()->where('tenant_id', $tenantId)->whereNotNull('score')->avg('score'), 2),
            ],
            'xapi_events' => [
                'statements' => XapiStatement::query()->where('tenant_id', $tenantId)->count(),
                'verbs' => XapiStatement::query()->where('tenant_id', $tenantId)->get()->groupBy(fn ($statement) => $statement->verb['display']['en-US'] ?? $statement->verb['id'] ?? 'unknown')->map->count(),
            ],
            'lti_launches' => [
                'registrations' => LtiRegistration::query()->where('tenant_id', $tenantId)->count(),
                'launches' => LtiLaunch::query()->where('tenant_id', $tenantId)->count(),
            ],
            'external_tools' => ExternalTool::query()->where('tenant_id', $tenantId)->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category'),
        ];
    }

    public function resolveUserId(?string $email): int
    {
        return (int) (LmsUser::query()->when($email, fn ($query) => $query->where('email', $email))->value('id') ?: 1);
    }
}
