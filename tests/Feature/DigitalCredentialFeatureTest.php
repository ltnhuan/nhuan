<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\BadgeIssue;
use App\Models\BadgeRule;
use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Models\CertificateTemplate;
use App\Models\LearningCompletion;
use App\Models\LmsUser;
use App\Services\DigitalCredentialService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DigitalCredentialFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected LmsUser $student;
    protected Certificate $certificate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        $template = CertificateTemplate::query()->create([
            'tenant_id' => 1,
            'code' => 'TEST-TPL',
            'name' => 'Test Template',
            'status' => 'published',
            'canvas_schema' => ['elements' => [['type' => 'qr', 'field' => 'qr_payload']]],
            'dynamic_fields' => ['learner_name', 'qr_payload', 'verification_hash'],
            'settings' => ['hash_verify' => true],
        ]);
        $this->certificate = Certificate::query()->create([
            'tenant_id' => 1,
            'certificate_template_id' => $template->id,
            'code' => 'TEST-CERT',
            'title' => 'Test Certificate',
            'credential_type' => 'course_completion',
            'issuer_name' => 'EraLMS',
            'rules' => ['completion' => true],
            'status' => 'active',
        ]);
    }

    public function test_issue_certificate(): void
    {
        $issue = app(DigitalCredentialService::class)->issueCertificate(1, [
            'certificate_id' => $this->certificate->id,
            'user_id' => $this->student->id,
            'issue_code' => 'CERT-QR-TEST',
        ]);

        $this->assertSame('CERT-QR-TEST', $issue->issue_code);
        $this->assertNotEmpty($issue->verification_hash);
        $this->assertNotEmpty($issue->portfolio_item_id);
        $this->assertStringContainsString('/CERT-QR-TEST', $issue->qr_payload);
    }

    public function test_verify_qr_certificate(): void
    {
        app(DigitalCredentialService::class)->issueCertificate(1, [
            'certificate_id' => $this->certificate->id,
            'user_id' => $this->student->id,
            'issue_code' => 'CERT-VERIFY-001',
        ]);

        $response = $this->withHeader('X-Tenant-Code', 'VABIS')->getJson('/api/v1/credentials/verify/CERT-VERIFY-001');

        $response->assertOk();
        $this->assertTrue($response->json('valid'));
        $this->assertSame('CERT-VERIFY-001', $response->json('certificate.issue_code'));
    }

    public function test_revoke_certificate(): void
    {
        $issue = app(DigitalCredentialService::class)->issueCertificate(1, [
            'certificate_id' => $this->certificate->id,
            'user_id' => $this->student->id,
            'issue_code' => 'CERT-REVOKE-001',
        ]);

        app(DigitalCredentialService::class)->revokeCertificate($issue, 'Duplicate issue');

        $verified = app(DigitalCredentialService::class)->verifyCertificate(1, 'CERT-REVOKE-001');
        $this->assertFalse($verified['valid']);
        $this->assertSame('revoked', CertificateIssue::query()->where('issue_code', 'CERT-REVOKE-001')->value('status'));
    }

    public function test_badge_auto_issue(): void
    {
        $badge = Badge::query()->create(['tenant_id' => 1, 'code' => 'AUTO-BADGE', 'name' => 'Completion Badge', 'badge_type' => 'completion', 'status' => 'active']);
        BadgeRule::query()->create(['tenant_id' => 1, 'badge_id' => $badge->id, 'rule_type' => 'completion', 'conditions' => ['min_progress' => 100], 'auto_issue' => true, 'status' => 'active']);
        $completion = LearningCompletion::query()->create(['tenant_id' => 1, 'user_id' => $this->student->id, 'course_id' => 55, 'completion_type' => 'course', 'status' => 'completed', 'progress_percent' => 100, 'score' => 88, 'completed_at' => now(), 'source' => 'test', 'metadata' => []]);

        $issued = app(DigitalCredentialService::class)->autoIssueBadgesForCompletion($completion);

        $this->assertCount(1, $issued);
        $this->assertTrue(BadgeIssue::query()->where('tenant_id', 1)->where('badge_id', $badge->id)->where('user_id', $this->student->id)->exists());
    }
}
