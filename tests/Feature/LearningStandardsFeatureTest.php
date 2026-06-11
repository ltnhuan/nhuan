<?php

namespace Tests\Feature;

use App\Models\LtiRegistration;
use App\Models\ScormAttempt;
use App\Models\ScormPackage;
use App\Models\XapiStatement;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearningStandardsFeatureTest extends TestCase
{
    use RefreshDatabase;

    private array $headers = ['X-Tenant-Code' => 'VABIS', 'X-Demo-User-Email' => 'admin.lms@vabis.edu.vn'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
    }

    public function test_upload_scorm_package_zip(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('orientation.zip', 64, 'application/zip');

        $response = $this->withHeaders($this->headers)->post('/api/v1/learning-standards/scorm/packages', [
            'package' => $file,
            'title' => 'Orientation SCORM',
            'standard' => 'scorm_2004',
            'launch_path' => 'story.html',
        ]);

        $response->assertCreated()->assertJsonPath('title', 'Orientation SCORM')->assertJsonPath('standard', 'scorm_2004');
        $this->assertDatabaseHas('scorm_packages', ['title' => 'Orientation SCORM', 'launch_path' => 'story.html']);
    }

    public function test_launch_scorm_creates_attempt(): void
    {
        $package = $this->package();

        $response = $this->withHeaders($this->headers)->postJson("/api/v1/learning-standards/scorm/packages/{$package->id}/launch", [
            'user_id' => 1,
        ]);

        $response->assertCreated()->assertJsonPath('attempt.package_id', $package->id);
        $this->assertDatabaseHas('scorm_attempts', ['package_id' => $package->id, 'user_id' => 1, 'status' => 'launched']);
        $this->assertDatabaseHas('scorm_events', ['package_id' => $package->id, 'event_type' => 'launch']);
    }

    public function test_completion_tracking_updates_progress_score_and_completion(): void
    {
        $attempt = ScormAttempt::query()->create([
            'tenant_id' => 1,
            'package_id' => $this->package()->id,
            'user_id' => 1,
            'standard' => 'scorm_1_2',
            'status' => 'launched',
            'completion_status' => 'incomplete',
            'started_at' => now(),
        ]);

        $this->withHeaders($this->headers)->postJson("/api/v1/learning-standards/scorm/attempts/{$attempt->id}/track", [
            'progress' => 100,
            'score' => 88,
            'completion_status' => 'completed',
            'success_status' => 'passed',
            'event_type' => 'completion',
        ])->assertOk()->assertJsonPath('completion_status', 'completed')->assertJsonPath('score', 88);

        $this->assertDatabaseHas('scorm_attempts', ['id' => $attempt->id, 'status' => 'completed', 'completion_status' => 'completed']);
        $this->assertDatabaseHas('scorm_events', ['attempt_id' => $attempt->id, 'event_type' => 'completion']);
    }

    public function test_xapi_statement_store_actor_verb_object(): void
    {
        $statement = [
            'actor' => ['name' => 'Student'],
            'verb' => ['id' => 'https://adlnet.gov/expapi/verbs/completed', 'display' => ['en-US' => 'completed']],
            'object' => ['id' => 'https://eralms.test/lessons/1', 'definition' => ['name' => ['en-US' => 'Lesson']]],
        ];

        $response = $this->withHeaders($this->headers)->postJson('/api/v1/learning-standards/xapi/statements', $statement);

        $response->assertCreated()->assertJsonPath('actor.name', 'Student')->assertJsonPath('verb.display.en-US', 'completed');
        $this->assertSame('Lesson', XapiStatement::query()->first()->object['definition']['name']['en-US']);
    }

    public function test_lti_launch_records_launch_claims(): void
    {
        $registration = LtiRegistration::query()->create([
            'tenant_id' => 1,
            'name' => 'Virtual Lab',
            'issuer' => 'https://lab.example.test',
            'client_id' => 'lab-client',
            'deployment_id' => 'lab-deploy',
            'login_url' => 'https://lab.example.test/lti/login',
            'launch_url' => 'https://lab.example.test/lti/launch',
            'tool_type' => 'tool_provider',
            'status' => 'active',
        ]);

        $response = $this->withHeaders($this->headers)->postJson("/api/v1/learning-standards/lti/registrations/{$registration->id}/launch", [
            'user_id' => 1,
            'resource_link_id' => 'lab-activity-1',
            'roles' => ['Learner'],
        ]);

        $response->assertCreated()->assertJsonPath('claims.message_type', 'LtiResourceLinkRequest')->assertJsonPath('redirect_url', 'https://lab.example.test/lti/launch');
        $this->assertDatabaseHas('lti_launches', ['registration_id' => $registration->id, 'resource_link_id' => 'lab-activity-1', 'status' => 'launched']);
    }

    private function package(): ScormPackage
    {
        return ScormPackage::query()->create([
            'tenant_id' => 1,
            'title' => 'Safety SCORM',
            'standard' => 'scorm_1_2',
            'version' => '1.2',
            'launch_path' => 'index.html',
            'zip_path' => 'scorm/safety.zip',
            'file_size' => 128,
            'status' => 'ready',
        ]);
    }
}
