<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\BadgeRule;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\CredentialSkillMap;
use App\Models\LmsUser;
use App\Services\DigitalCredentialService;
use Illuminate\Database\Seeder;

class DigitalCredentialSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        $service = app(DigitalCredentialService::class);

        for ($i = 1; $i <= 50; $i++) {
            $template = CertificateTemplate::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => 'CERT-TPL-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
                [
                    'name' => 'Mẫu chứng chỉ số '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'type' => $i % 5 === 0 ? 'skill_certificate' : 'certificate',
                    'language' => $i % 4 === 0 ? 'en' : 'vi',
                    'status' => 'published',
                    'canvas_schema' => [
                        'width' => 1123,
                        'height' => 794,
                        'elements' => [
                            ['type' => 'text', 'field' => 'certificate_title', 'x' => 120, 'y' => 170, 'fontSize' => 34],
                            ['type' => 'text', 'field' => 'learner_name', 'x' => 180, 'y' => 330, 'fontSize' => 44],
                            ['type' => 'qr', 'field' => 'qr_payload', 'x' => 912, 'y' => 604, 'size' => 120],
                            ['type' => 'image', 'field' => 'signature_image', 'x' => 170, 'y' => 610],
                        ],
                    ],
                    'dynamic_fields' => ['learner_name','certificate_title','course_title','issued_at','qr_payload','verification_hash','signature_image'],
                    'signature_image_path' => '/images/signatures/default-'.$i.'.png',
                    'translations' => ['vi' => ['title' => 'Chứng nhận hoàn thành'], 'en' => ['title' => 'Certificate of Completion']],
                    'settings' => ['drag_drop' => true, 'qr_code' => true, 'hash_verify' => true, 'multi_language' => true],
                ]
            );

            Certificate::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => 'CERT-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
                [
                    'certificate_template_id' => $template->id,
                    'title' => match ($i % 4) {
                        0 => 'Chứng chỉ hoàn thành Learning Path',
                        1 => 'Chứng chỉ hoàn thành khóa học',
                        2 => 'Chứng chỉ đạt chuẩn Competency',
                        default => 'Chứng chỉ xuất sắc theo điểm số',
                    }.' '.$i,
                    'description' => 'Chứng chỉ điện tử có QR, hash verify, chữ ký số ảnh và metadata SIS.',
                    'credential_type' => match ($i % 4) { 0 => 'learning_path', 1 => 'course_completion', 2 => 'competency', default => 'grade_excellence' },
                    'issuer_name' => 'EraLMS Enterprise',
                    'rules' => ['completion' => true, 'min_score' => 60 + ($i % 30), 'competency_status' => 'achieved'],
                    'metadata' => ['blockchain_ready' => true, 'sis_sync' => true],
                    'status' => 'active',
                ]
            );
        }

        $types = ['completion','excellence','attendance','competency','ai_skill'];
        for ($i = 1; $i <= 20; $i++) {
            $type = $types[$i % count($types)];
            $badge = Badge::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => 'BADGE-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
                [
                    'name' => match ($type) {
                        'completion' => 'Completion Badge',
                        'excellence' => 'Excellence Badge',
                        'attendance' => 'Attendance Badge',
                        'competency' => 'Competency Badge',
                        default => 'AI Skill Badge',
                    }.' '.$i,
                    'badge_type' => $type,
                    'description' => 'Badge điện tử tự động cấp theo rule engine.',
                    'image_url' => '/images/badges/'.$type.'.png',
                    'criteria' => ['rule_type' => $type],
                    'metadata' => ['open_badge_ready' => true],
                    'status' => 'active',
                ]
            );
            BadgeRule::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'badge_id' => $badge->id, 'rule_type' => $type],
                ['source_type' => 'course', 'source_id' => null, 'conditions' => ['min_progress' => 100, 'min_score' => 85, 'min_attendance' => 80, 'status' => 'achieved'], 'auto_issue' => true, 'status' => 'active']
            );
        }

        foreach ([
            ['MC-AI-PROMPT', 'AI Prompting Micro Credential', 'skill_based', 'Artificial Intelligence'],
            ['MC-WELD-SAFE', 'Welding Safety Micro Credential', 'industry_based', 'Mechanical'],
            ['MC-OUTCOME-COMM', 'Workplace Communication Outcome', 'outcome_based', 'General'],
        ] as [$code, $title, $type, $industry]) {
            $credential = $service->issueMicroCredential($tenantId, ['code' => $code, 'title' => $title, 'credential_type' => $type, 'industry' => $industry, 'level' => 'foundation', 'outcome_statement' => 'Learner demonstrates verified workplace-ready capability.', 'skills' => [['skill_name' => $title, 'required_score' => 80]]]);
            CredentialSkillMap::query()->firstOrCreate(['tenant_id' => $tenantId, 'credential_type' => 'micro_credential', 'credential_id' => $credential->id, 'skill_name' => $title], ['required_score' => 80, 'metadata' => []]);
        }

        $students = LmsUser::query()->where('tenant_id', $tenantId)->where('user_type', 'student')->get();
        if ($students->isEmpty()) {
            return;
        }

        $courses = Course::query()->where('tenant_id', $tenantId)->pluck('id')->values();
        $certificates = Certificate::query()->where('tenant_id', $tenantId)->pluck('id')->values();
        $badges = Badge::query()->where('tenant_id', $tenantId)->pluck('id')->values();

        for ($i = 1; $i <= 500; $i++) {
            $student = $students[($i - 1) % $students->count()];
            $service->issueCertificate($tenantId, [
                'certificate_id' => $certificates[($i - 1) % $certificates->count()],
                'user_id' => $student->id,
                'course_id' => $courses->isNotEmpty() ? $courses[($i - 1) % $courses->count()] : null,
                'issue_code' => 'CERT-DEMO-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'field_values' => ['course_title' => 'Khóa học demo '.(($i % 30) + 1)],
                'sis_payload' => ['sis_user_id' => $student->sis_user_id, 'sync_status' => 'queued'],
            ]);
        }

        for ($i = 1; $i <= 500; $i++) {
            $student = $students[($i - 1) % $students->count()];
            $service->issueBadge($tenantId, [
                'badge_id' => $badges[($i - 1) % $badges->count()],
                'user_id' => $student->id,
                'course_id' => 100000 + $i,
                'evidence' => ['demo_issue' => true, 'score' => 70 + ($i % 30)],
            ]);
        }
    }
}
