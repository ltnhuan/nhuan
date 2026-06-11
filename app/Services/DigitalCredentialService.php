<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\BadgeIssue;
use App\Models\BadgeRule;
use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Models\CertificateTemplate;
use App\Models\CertificateVerification;
use App\Models\CredentialEvent;
use App\Models\CredentialSkillMap;
use App\Models\DigitalPortfolio;
use App\Models\LearningCompletion;
use App\Models\LmsUser;
use App\Models\MicroCredential;
use App\Models\PortfolioItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DigitalCredentialService
{
    public function issueCertificate(int $tenantId, array $data): CertificateIssue
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $certificate = Certificate::query()->where('tenant_id', $tenantId)->findOrFail($data['certificate_id']);
            $learner = LmsUser::query()->where('tenant_id', $tenantId)->findOrFail($data['user_id']);
            $this->assertCertificateEligibility($tenantId, $learner->id, $data['course_id'] ?? null);
            $code = $data['issue_code'] ?? $this->nextCode('CERT');
            $issuedAt = $data['issued_at'] ?? now();
            $hash = $this->hashPayload($tenantId, $code, $learner->id, $certificate->id, $issuedAt);
            $url = $this->verificationUrl($code);

            $issue = CertificateIssue::query()->updateOrCreate(
                ['issue_code' => $code],
                [
                    'tenant_id' => $tenantId,
                    'certificate_id' => $certificate->id,
                    'certificate_template_id' => $data['certificate_template_id'] ?? $certificate->certificate_template_id,
                    'user_id' => $learner->id,
                    'course_id' => $data['course_id'] ?? null,
                    'learning_path_id' => $data['learning_path_id'] ?? null,
                    'learner_name' => $data['learner_name'] ?? $learner->full_name,
                    'certificate_title' => $data['certificate_title'] ?? $certificate->title,
                    'status' => 'issued',
                    'issued_at' => $issuedAt,
                    'expires_at' => $data['expires_at'] ?? null,
                    'qr_payload' => $url,
                    'verification_hash' => $hash,
                    'verification_url' => $url,
                    'language' => $data['language'] ?? 'vi',
                    'field_values' => $data['field_values'] ?? [],
                    'sis_payload' => $data['sis_payload'] ?? [],
                    'blockchain_status' => 'ready_to_anchor',
                    'blockchain_network' => $data['blockchain_network'] ?? null,
                    'blockchain_tx_hash' => $data['blockchain_tx_hash'] ?? null,
                ]
            );

            $issue->forceFill(['portfolio_item_id' => $this->publishPortfolioItem($tenantId, $learner->id, 'certificate', $issue)->id])->save();
            $this->event($tenantId, 'certificate', $issue->id, $learner->id, 'certificate_issued', ['issue_code' => $code, 'hash' => $hash]);

            return $issue->fresh(['certificate.template', 'learner', 'portfolioItem']);
        });
    }

    public function verifyCertificate(?int $tenantId, string $code, array $viewer = []): array
    {
        $query = CertificateIssue::query()->where('issue_code', $code)->with(['certificate', 'learner']);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $issue = $query->first();
        $valid = $issue && $issue->status === 'issued' && ! $issue->revoked_at && (! $issue->expires_at || $issue->expires_at->isFuture());
        $status = $issue ? ($valid ? 'valid' : $issue->status) : 'not_found';

        CertificateVerification::query()->create([
            'tenant_id' => $issue?->tenant_id ?? $tenantId,
            'certificate_issue_id' => $issue?->id,
            'issue_code' => $code,
            'verification_hash' => $issue?->verification_hash,
            'valid' => (bool) $valid,
            'status' => $status,
            'viewer_ip' => $viewer['ip'] ?? null,
            'viewer_user_agent' => $viewer['user_agent'] ?? null,
            'metadata' => ['source' => $viewer['source'] ?? 'verify_portal'],
            'created_at' => now(),
        ]);

        return [
            'valid' => (bool) $valid,
            'status' => $status,
            'learner' => $issue?->learner?->only(['id', 'code', 'full_name']),
            'certificate' => $issue ? [
                'issue_code' => $issue->issue_code,
                'title' => $issue->certificate_title,
                'issued_at' => $issue->issued_at?->toDateString(),
                'expires_at' => $issue->expires_at?->toDateString(),
                'verification_hash' => $issue->verification_hash,
                'verification_url' => $issue->verification_url,
                'qr' => $issue->qr_payload,
                'blockchain_status' => $issue->blockchain_status,
                'blockchain_tx_hash' => $issue->blockchain_tx_hash,
            ] : null,
        ];
    }

    public function revokeCertificate(CertificateIssue $issue, string $reason): CertificateIssue
    {
        $issue->forceFill(['status' => 'revoked', 'revocation_reason' => $reason, 'revoked_at' => now()])->save();
        if ($issue->portfolio_item_id) {
            PortfolioItem::query()->whereKey($issue->portfolio_item_id)->update(['status' => 'revoked']);
        }
        $this->event($issue->tenant_id, 'certificate', $issue->id, $issue->user_id, 'certificate_revoked', ['reason' => $reason]);
        return $issue->fresh();
    }

    public function issueBadge(int $tenantId, array $data): BadgeIssue
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $badge = Badge::query()->where('tenant_id', $tenantId)->findOrFail($data['badge_id']);
            $learner = LmsUser::query()->where('tenant_id', $tenantId)->findOrFail($data['user_id']);
            $code = $data['issue_code'] ?? $this->nextCode('BADGE');
            $hash = $this->hashPayload($tenantId, $code, $learner->id, $badge->id, now());
            $issue = BadgeIssue::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'badge_id' => $badge->id, 'user_id' => $learner->id, 'course_id' => $data['course_id'] ?? null],
                [
                    'issue_code' => $code,
                    'status' => 'issued',
                    'issued_at' => $data['issued_at'] ?? now(),
                    'verification_hash' => $hash,
                    'verification_url' => $this->verificationUrl($code),
                    'evidence' => $data['evidence'] ?? [],
                ]
            );
            $issue->forceFill(['portfolio_item_id' => $this->publishPortfolioItem($tenantId, $learner->id, 'badge', $issue)->id])->save();
            $this->event($tenantId, 'badge', $issue->id, $learner->id, 'badge_issued', ['issue_code' => $issue->issue_code]);
            return $issue->fresh(['badge', 'learner']);
        });
    }

    public function issueMicroCredential(int $tenantId, array $data): MicroCredential
    {
        $credential = MicroCredential::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => $data['code']],
            [
                'title' => $data['title'],
                'credential_type' => $data['credential_type'] ?? 'skill_based',
                'industry' => $data['industry'] ?? null,
                'level' => $data['level'] ?? null,
                'outcome_statement' => $data['outcome_statement'] ?? null,
                'criteria' => $data['criteria'] ?? [],
                'metadata' => $data['metadata'] ?? [],
                'status' => $data['status'] ?? 'active',
            ]
        );

        foreach ($data['skills'] ?? [] as $skill) {
            CredentialSkillMap::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'credential_type' => 'micro_credential', 'credential_id' => $credential->id, 'skill_name' => $skill['skill_name']],
                ['skill_definition_id' => $skill['skill_definition_id'] ?? null, 'skill_code' => $skill['skill_code'] ?? null, 'required_score' => $skill['required_score'] ?? null, 'metadata' => $skill['metadata'] ?? []]
            );
        }

        $this->event($tenantId, 'micro_credential', $credential->id, null, 'micro_credential_upserted', ['code' => $credential->code]);
        return $credential->fresh();
    }

    public function autoIssueBadgesForCompletion(LearningCompletion $completion): array
    {
        $rules = BadgeRule::query()
            ->where('tenant_id', $completion->tenant_id)
            ->where('auto_issue', true)
            ->where('status', 'active')
            ->where(function ($query) use ($completion) {
                $query->whereNull('source_id')->orWhere('source_id', $completion->course_id);
            })
            ->get();

        $issued = [];
        foreach ($rules as $rule) {
            if ($this->matchesRule($rule, $completion)) {
                $issued[] = $this->issueBadge($completion->tenant_id, [
                    'badge_id' => $rule->badge_id,
                    'user_id' => $completion->user_id,
                    'course_id' => $completion->course_id,
                    'evidence' => ['completion_id' => $completion->id, 'rule_type' => $rule->rule_type],
                ]);
            }
        }
        return $issued;
    }

    public function analytics(int $tenantId): array
    {
        return [
            'total_certificates' => CertificateIssue::query()->where('tenant_id', $tenantId)->count(),
            'total_badges' => BadgeIssue::query()->where('tenant_id', $tenantId)->count(),
            'verify_count' => CertificateVerification::query()->where('tenant_id', $tenantId)->count(),
            'revoked_certificates' => CertificateIssue::query()->where('tenant_id', $tenantId)->where('status', 'revoked')->count(),
            'micro_credentials' => MicroCredential::query()->where('tenant_id', $tenantId)->count(),
        ];
    }

    public function wallet(int $tenantId, int $userId): array
    {
        return [
            'certificates' => CertificateIssue::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->latest()->get(),
            'badges' => BadgeIssue::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->with('badge')->latest()->get(),
            'portfolio_items' => PortfolioItem::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->whereIn('item_type', ['certificate', 'badge', 'micro_credential', 'skill_credential'])->latest()->get(),
        ];
    }

    public function defaultCertificateBlueprint(int $tenantId): CertificateTemplate
    {
        return CertificateTemplate::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'CERT-BUILDER-DEFAULT'],
            [
                'name' => 'Certificate Builder Default',
                'type' => 'certificate',
                'language' => 'vi',
                'status' => 'published',
                'canvas_schema' => ['width' => 1123, 'height' => 794, 'elements' => [['type' => 'text', 'field' => 'learner_name', 'x' => 240, 'y' => 330], ['type' => 'qr', 'field' => 'qr_payload', 'x' => 920, 'y' => 610]]],
                'dynamic_fields' => ['learner_name', 'certificate_title', 'issued_at', 'course_title', 'qr_payload', 'verification_hash'],
                'translations' => ['en' => ['title' => 'Certificate of Completion'], 'vi' => ['title' => 'Chứng nhận hoàn thành']],
                'settings' => ['drag_drop' => true, 'hash_verify' => true, 'multi_language' => true, 'signature_image' => true],
            ]
        );
    }

    private function publishPortfolioItem(int $tenantId, int $userId, string $type, CertificateIssue|BadgeIssue $issue): PortfolioItem
    {
        if ($type === 'badge') {
            $issue->loadMissing('badge');
        }
        $portfolio = DigitalPortfolio::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId],
            ['title' => 'Learner Credential Wallet', 'summary' => 'Digital credentials issued by EraLMS.', 'status' => 'published', 'settings' => []]
        );
        $title = $type === 'certificate' ? $issue->certificate_title : ($issue->badge?->name ?? 'Digital Badge');

        return PortfolioItem::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'portfolio_id' => $portfolio->id, 'verification_code' => $issue->issue_code],
            ['user_id' => $userId, 'item_type' => $type, 'title' => $title, 'description' => 'Digital credential issued by EraLMS.', 'issuer' => 'EraLMS', 'evidence_url' => $issue->verification_url, 'issued_at' => $issue->issued_at, 'visibility' => 'public', 'status' => 'published', 'metadata' => ['hash' => $issue->verification_hash]]
        );
    }

    private function matchesRule(BadgeRule $rule, LearningCompletion $completion): bool
    {
        $conditions = $rule->conditions ?? [];
        return match ($rule->rule_type) {
            'completion' => $completion->status === 'completed' || (float) $completion->progress_percent >= (float) ($conditions['min_progress'] ?? 100),
            'excellence' => (float) $completion->score >= (float) ($conditions['min_score'] ?? 90),
            'attendance' => (float) data_get($completion->metadata, 'attendance_percent', 0) >= (float) ($conditions['min_attendance'] ?? 80),
            'competency', 'ai_skill' => data_get($completion->metadata, 'competency_status') === ($conditions['status'] ?? 'achieved'),
            default => false,
        };
    }

    private function assertCertificateEligibility(int $tenantId, int $userId, ?int $courseId): void
    {
        if (! $courseId) {
            return;
        }

        $completed = LearningCompletion::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('completion_type', 'course')
            ->where('status', 'completed')
            ->exists();

        if (! $completed) {
            throw new \RuntimeException('Không thể cấp chứng chỉ khi người học chưa hoàn thành khóa học.');
        }

        $summaries = DB::table('grade_summaries')
            ->join('gradebooks', 'gradebooks.id', '=', 'grade_summaries.gradebook_id')
            ->where('grade_summaries.tenant_id', $tenantId)
            ->where('grade_summaries.user_id', $userId)
            ->where('gradebooks.course_id', $courseId);

        if ((clone $summaries)->exists() && ! (clone $summaries)->whereIn('pass_status', ['passed', 'pass'])->exists()) {
            throw new \RuntimeException('Không thể cấp chứng chỉ khi điểm tổng kết chưa đạt.');
        }
    }

    private function nextCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
    }

    private function verificationUrl(string $code): string
    {
        return rtrim(config('eralms.credentials.verify_base_url', 'https://verify.eralms.vn/certificate'), '/').'/'.$code;
    }

    private function hashPayload(int $tenantId, string $code, int $userId, int $credentialId, mixed $issuedAt): string
    {
        return hash('sha256', implode('|', [$tenantId, $code, $userId, $credentialId, (string) $issuedAt, config('app.key')]));
    }

    private function event(?int $tenantId, string $type, ?int $credentialId, ?int $userId, string $eventType, array $payload = []): void
    {
        CredentialEvent::query()->create(['tenant_id' => $tenantId, 'credential_type' => $type, 'credential_id' => $credentialId, 'user_id' => $userId, 'event_type' => $eventType, 'source' => 'eralms', 'payload' => $payload, 'created_at' => now()]);
    }
}
