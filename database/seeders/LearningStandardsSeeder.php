<?php

namespace Database\Seeders;

use App\Models\ExternalTool;
use App\Models\LtiRegistration;
use App\Models\ScormAttempt;
use App\Models\ScormEvent;
use App\Models\ScormPackage;
use App\Models\XapiStatement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LearningStandardsSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;

        $scorm = ScormPackage::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'title' => 'SCORM Safety Orientation Sample'],
            [
                'course_id' => 1,
                'standard' => 'scorm_1_2',
                'version' => '1.2',
                'launch_path' => 'index.html',
                'zip_path' => 'seed/scorm/safety-orientation.zip',
                'file_size' => 245760,
                'checksum' => hash('sha256', 'seed-scorm-safety-orientation'),
                'status' => 'ready',
                'manifest' => ['identifier' => 'safety-orientation', 'organizations' => ['Safety Orientation'], 'resources' => [['href' => 'index.html']]],
                'metadata' => ['source' => 'seed', 'supports' => ['progress', 'score', 'completion']],
                'uploaded_by' => 1,
            ]
        );

        $attempt = ScormAttempt::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'package_id' => $scorm->id, 'user_id' => 2],
            [
                'standard' => 'scorm_1_2',
                'status' => 'completed',
                'progress' => 100,
                'score' => 92,
                'completion_status' => 'completed',
                'success_status' => 'passed',
                'session_time_seconds' => 1260,
                'runtime_data' => ['cmi.core.lesson_location' => 'summary'],
                'started_at' => now()->subDay(),
                'completed_at' => now()->subDay()->addMinutes(21),
            ]
        );

        ScormEvent::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'attempt_id' => $attempt->id, 'event_type' => 'completion'],
            ['package_id' => $scorm->id, 'progress' => 100, 'score' => 92, 'completion_status' => 'completed', 'payload' => ['source' => 'seed'], 'occurred_at' => now()->subDay()->addMinutes(21)]
        );

        foreach ([
            ['00000000-0000-4000-8000-000000000101', 'Student', 'completed', 'Lesson', ['score' => ['scaled' => 0.92]]],
            ['00000000-0000-4000-8000-000000000102', 'Student', 'experienced', 'Virtual Lab', ['completion' => true]],
            ['00000000-0000-4000-8000-000000000103', 'Student', 'passed', 'External Exam', ['score' => ['raw' => 86, 'max' => 100]]],
        ] as $i => [$uuid, $actor, $verb, $object, $result]) {
            XapiStatement::query()->updateOrCreate(
                ['statement_uuid' => $uuid],
                [
                    'tenant_id' => $tenantId,
                    'actor' => ['name' => $actor, 'account' => ['homePage' => 'https://eralms.example.test', 'name' => 'student-'.$i]],
                    'verb' => ['id' => 'https://adlnet.gov/expapi/verbs/'.Str::slug($verb), 'display' => ['en-US' => $verb]],
                    'object' => ['id' => 'https://eralms.example.test/activity/'.Str::slug($object), 'definition' => ['name' => ['en-US' => $object]]],
                    'result' => $result,
                    'context' => ['platform' => 'EraLMS'],
                    'raw_statement' => ['actor' => $actor, 'verb' => $verb, 'object' => $object],
                    'stored_at' => now()->subHours(3 - $i),
                    'timestamp' => now()->subHours(3 - $i),
                ]
            );
        }

        foreach ([
            ['Virtual Lab LTI', 'https://lab.example.test', 'lab-client', 'lab-deploy', 'https://lab.example.test/lti/login', 'https://lab.example.test/lti/launch'],
            ['Simulation Provider', 'https://simulation.example.test', 'sim-client', 'sim-deploy', 'https://simulation.example.test/lti/login', 'https://simulation.example.test/lti/launch'],
            ['External Exam Provider', 'https://exam.example.test', 'exam-client', 'exam-deploy', 'https://exam.example.test/lti/login', 'https://exam.example.test/lti/launch'],
            ['Publisher Content', 'https://publisher.example.test', 'publisher-client', 'publisher-deploy', 'https://publisher.example.test/lti/login', 'https://publisher.example.test/lti/launch'],
        ] as [$name, $issuer, $client, $deployment, $login, $launch]) {
            LtiRegistration::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'client_id' => $client, 'deployment_id' => $deployment],
                ['name' => $name, 'issuer' => $issuer, 'login_url' => $login, 'launch_url' => $launch, 'jwks_url' => $issuer.'/jwks.json', 'tool_type' => 'tool_provider', 'status' => 'active', 'scopes' => ['openid', 'profile', 'email'], 'settings' => ['tool_consumer' => 'EraLMS']]
            );
        }

        foreach ([
            ['Zoom', 'zoom', 'Zoom', 'https://zoom.us/start/videomeeting', ['live_session', 'attendance']],
            ['Microsoft Teams', 'teams', 'Microsoft', 'https://teams.microsoft.com/l/meetup-join/demo', ['live_session', 'recording']],
            ['Google Meet', 'google_meet', 'Google', 'https://meet.google.com/new', ['live_session']],
            ['Virtual Lab', 'lab', 'EraLab', 'https://lab.example.test/launch', ['virtual_lab', 'lti']],
            ['AI Tutor Tool', 'ai_tool', 'EraAI', 'https://ai.example.test/tools/tutor', ['ai_feedback', 'rubric_assist']],
        ] as [$name, $category, $provider, $url, $capabilities]) {
            ExternalTool::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => $name],
                ['category' => $category, 'provider' => $provider, 'launch_type' => 'external_url', 'launch_url' => $url, 'status' => 'active', 'capabilities' => $capabilities, 'settings' => ['registry' => 'learning_standards']]
            );
        }
    }
}
