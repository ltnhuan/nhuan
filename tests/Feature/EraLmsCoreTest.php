<?php

namespace Tests\Feature;

use App\Services\LearningEventService;
use App\Services\LearningPathRuleService;
use PHPUnit\Framework\TestCase;

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
}
