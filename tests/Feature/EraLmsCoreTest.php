<?php

namespace Tests\Feature;

use App\Http\Middleware\TenantResolver;
use App\Services\Core\CorePermissionService;
use App\Services\LearningEventService;
use App\Services\LearningPathRuleService;
use App\Services\TenantContext;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class EraLmsCoreTest extends TestCase
{
    public function test_learning_rule_requires_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new LearningPathRuleService())->validateRuleConfig(['requires' => [[]]]);
    }

    public function test_fake_video_progress_is_detected(): void
    {
        $service = new LearningEventService();
        $this->assertTrue($service->detectFakeProgress(['event_type' => 'video_progress', 'event_value' => 95, 'metadata' => ['previous_percent' => 5, 'elapsed_seconds' => 4]]));
    }

    public function test_tenant_resolver_can_extract_subdomain_code(): void
    {
        $resolver = new TenantResolver(new TenantContext());
        $method = (new ReflectionClass($resolver))->getMethod('subdomainCode');
        $method->setAccessible(true);

        $this->assertSame('vabis', $method->invoke($resolver, 'vabis.eralms.local'));
        $this->assertNull($method->invoke($resolver, 'localhost'));
    }

    public function test_permission_scope_match_allows_global_and_blocks_wrong_campus(): void
    {
        $service = new CorePermissionService();
        $method = (new ReflectionClass($service))->getMethod('scopeMatches');
        $method->setAccessible(true);

        $permission = ['tenant_id' => 1, 'campus_id' => null, 'academic_unit_id' => null, 'course_id' => null, 'class_id' => null];
        $this->assertTrue($method->invoke($service, $permission, ['tenant_id' => 1, 'campus_id' => 2]));

        $campusPermission = ['tenant_id' => 1, 'campus_id' => 3, 'academic_unit_id' => null, 'course_id' => null, 'class_id' => null];
        $this->assertFalse($method->invoke($service, $campusPermission, ['tenant_id' => 1, 'campus_id' => 2]));
    }
}
