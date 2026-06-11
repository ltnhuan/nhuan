<?php

namespace Tests\Feature;

use App\Services\MoodleParityService;
use Tests\TestCase;

class MoodleParityFeatureTest extends TestCase
{
    public function test_moodle_parity_report_maps_existing_eralms_modules(): void
    {
        $report = app(MoodleParityService::class)->report();

        $this->assertGreaterThanOrEqual(10, $report['summary']['total']);
        $this->assertContains('course', collect($report['features'])->pluck('eralms_module')->all());
        $this->assertContains('gradebook', collect($report['features'])->pluck('eralms_module')->all());
        $this->assertContains('backup', collect($report['features'])->pluck('eralms_module')->all());
    }

    public function test_moodle_parity_sync_plan_updates_existing_and_tracks_planned_modules(): void
    {
        $plan = app(MoodleParityService::class)->syncPlan();

        $this->assertContains('course', $plan['updated_existing']);
        $this->assertContains('backup', $plan['planned_modules']);
    }
}
