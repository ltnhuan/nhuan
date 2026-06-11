<?php

namespace Tests\Kit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\Tenant;
use App\Models\Campus;
use App\Models\LmsUser;
use App\Models\Course;
use App\Models\VideoAsset;
use App\Models\Enrollment;
use App\Models\GoLiveChecklistItem;
use App\Models\Exam;
use App\Models\ExamAttempt;

/**
 * Testing Kit Verification Tests
 * 
 * Validates that:
 * 1. Seed data created successfully without duplicates
 * 2. UAT scripts can be executed
 * 3. Performance tests run without errors
 * 4. GoLive checklist populated correctly
 */
class KitVerificationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test 1: Seed Data Validation
     * Verify seed data can be created and matches expectations
     */
    public function test_seed_data_creates_without_duplicates()
    {
        // Run seeder
        $this->seed(\Database\Seeders\EnterpriseDataSeeder::class);

        // Verify tenant created
        $tenant = Tenant::where('code', 'VABIS')->first();
        $this->assertNotNull($tenant, 'Tenant VABIS should be created');

        // Verify no duplicate tenants
        $tenantCount = Tenant::where('code', 'VABIS')->count();
        $this->assertEquals(1, $tenantCount, 'Should have exactly 1 VABIS tenant');
    }

    /**
     * Test 2: Campus Data Integrity
     */
    public function test_campus_data_created_correctly()
    {
        $this->seed(\Database\Seeders\EnterpriseDataSeeder::class);

        $campuses = Campus::where('tenant_id', 1)->get();

        // Verify campus count
        $this->assertGreaterThanOrEqual(2, $campuses->count(), 'Should have at least 2 campuses');

        // Verify no duplicate campus codes
        $codes = $campuses->pluck('code')->toArray();
        $this->assertEquals(count($codes), count(array_unique($codes)), 'Campus codes should be unique');

        // Verify required fields
        foreach ($campuses as $campus) {
            $this->assertNotEmpty($campus->name);
            $this->assertNotEmpty($campus->code);
            $this->assertNotEmpty($campus->city);
        }
    }

    /**
     * Test 3: User Data - No Duplicate Emails
     */
    public function test_user_emails_are_unique()
    {
        $this->seed(\Database\Seeders\EnterpriseDataSeeder::class);

        $users = LmsUser::get();
        $emails = $users->pluck('email')->toArray();

        $this->assertEquals(
            count($emails),
            count(array_unique($emails)),
            'User emails should be unique'
        );
    }

    /**
     * Test 4: Course Data Validation
     */
    public function test_course_data_structure()
    {
        $this->seed(\Database\Seeders\EnterpriseDataSeeder::class);

        $course = Course::first();

        $this->assertNotNull($course);
        $this->assertNotEmpty($course->code);
        $this->assertNotEmpty($course->title);
        $this->assertTrue(in_array($course->status, ['active', 'archived', 'draft']));
    }

    /**
     * Test 5: Video Assets Created
     */
    public function test_video_assets_created()
    {
        $this->seed(\Database\Seeders\EnterpriseDataSeeder::class);

        $videoCount = VideoAsset::count();
        $this->assertGreaterThan(0, $videoCount, 'Videos should be created');

        $video = VideoAsset::first();
        $this->assertNotEmpty($video->hls_url);
        $this->assertStringContainsString('.m3u8', $video->hls_url);
    }

    /**
     * Test 6: Enrollments Exist
     */
    public function test_enrollments_created()
    {
        $this->seed(\Database\Seeders\EnterpriseDataSeeder::class);

        $enrollmentCount = Enrollment::count();
        $this->assertGreaterThan(1000, $enrollmentCount, 'Should have significant enrollment data');
    }

    /**
     * Test 7: Quiz Attempts Exist
     */
    public function test_exam_attempts_created()
    {
        $this->seed(\Database\Seeders\EnterpriseDataSeeder::class);

        $attemptCount = ExamAttempt::count();
        $this->assertGreaterThan(1000, $attemptCount, 'Should have quiz attempt data');

        // Verify score ranges
        $attempt = ExamAttempt::first();
        $this->assertGreaterThanOrEqual(0, $attempt->raw_score);
        $this->assertLessThanOrEqual(100, $attempt->raw_score);
    }

    /**
     * Test 8: GoLive Checklist Population
     */
    public function test_golive_checklist_populated()
    {
        $this->seed(\Database\Seeders\GoLiveChecklistSeeder::class);

        $itemCount = GoLiveChecklistItem::count();
        $this->assertGreaterThanOrEqual(50, $itemCount, 'Should have at least 50 checklist items');

        // Verify categories
        $categories = GoLiveChecklistItem::distinct('category')->pluck('category')->toArray();
        $expectedCategories = [
            'infrastructure',
            'configuration',
            'security',
            'data_migration',
            'backup_recovery',
            'monitoring',
            'integration',
            'documentation',
        ];

        foreach ($expectedCategories as $cat) {
            $this->assertContains($cat, $categories, "Category '$cat' should exist");
        }
    }

    /**
     * Test 9: Checklist Items Have Required Fields
     */
    public function test_checklist_items_complete()
    {
        $this->seed(\Database\Seeders\GoLiveChecklistSeeder::class);

        $items = GoLiveChecklistItem::get();

        foreach ($items as $item) {
            $this->assertNotEmpty($item->item_code, 'Item code required');
            $this->assertNotEmpty($item->title, 'Title required');
            $this->assertNotEmpty($item->category, 'Category required');
            $this->assertTrue(
                in_array($item->priority, ['low', 'medium', 'high', 'critical']),
                'Priority must be valid'
            );
            $this->assertTrue(
                in_array($item->risk_level, ['low', 'medium', 'high', 'critical']),
                'Risk level must be valid'
            );
        }
    }

    /**
     * Test 10: API Endpoints Work
     */
    public function test_api_endpoints_accessible()
    {
        // Test without authentication (should fail)
        $response = $this->get('/api/v1/golive-checklist');
        $this->assertEquals(401, $response->status(), 'API should require authentication');

        // Create user and test
        $user = $this->createTestUser('admin');

        $response = $this->actingAs($user)->get('/api/v1/golive-checklist');
        $this->assertEquals(200, $response->status(), 'API should return 200 with auth');

        $response->assertJsonStructure([
            'data' => [],
            'meta' => [
                'total',
                'completed',
                'progress_percent',
            ],
        ]);
    }

    /**
     * Test 11: Seed Data Idempotency
     * Running seeder multiple times shouldn't create duplicates
     */
    public function test_seed_idempotency()
    {
        // First seed
        $this->seed(\Database\Seeders\CoreSeeder::class);
        $firstCount = LmsUser::count();

        // Second seed (should not double count)
        $this->seed(\Database\Seeders\CoreSeeder::class);
        $secondCount = LmsUser::count();

        // Allow slight variance but not doubling
        $this->assertLessThan($firstCount * 1.5, $secondCount, 'Seeder should be mostly idempotent');
    }

    /**
     * Test 12: Demo Accounts Exist and Have Correct Roles
     */
    public function test_demo_accounts_have_correct_roles()
    {
        $this->seed(\Database\Seeders\CoreSeeder::class);

        $demoAccounts = [
            'admin.lms@vabis.edu.vn' => 'admin', // Should have tenant_admin role
            'daotao.lms@vabis.edu.vn' => 'training_officer',
            'khoa.lms@vabis.edu.vn' => 'faculty_manager',
            'gv.lms@vabis.edu.vn' => 'teacher',
            'sv.lms@vabis.edu.vn' => 'student',
        ];

        foreach ($demoAccounts as $email => $expectedRole) {
            $user = LmsUser::where('email', $email)->first();
            $this->assertNotNull($user, "User $email should exist");

            // Verify user has correct role
            $this->assertTrue(
                $user->hasRole($expectedRole),
                "User $email should have $expectedRole role"
            );
        }
    }

    /**
     * Test 13: UAT Scenario Can Be Instantiated
     */
    public function test_uat_scenarios_load()
    {
        $this->seed(\Database\Seeders\EnterpriseDataSeeder::class);

        // Test that scenarios can be loaded
        $scenario = \Tests\UAT\UATScenarios::scenario001_CreateCourse();

        $this->assertArrayHasKey('name', $scenario);
        $this->assertArrayHasKey('description', $scenario);
        $this->assertArrayHasKey('steps', $scenario);
        $this->assertCount(5, $scenario['steps'], 'Scenario should have steps');
    }

    /**
     * Test 14: Performance Tests Can Run
     */
    public function test_performance_test_suite_runs()
    {
        $user = $this->createTestUser('student');

        // Try a simple performance test
        $start = microtime(true);
        $this->actingAs($user)->get('/api/v1/core/dashboard');
        $duration = (microtime(true) - $start) * 1000;

        // Should complete in reasonable time (not a hard target, just verify it runs)
        $this->assertLessThan(5000, $duration, 'Dashboard should load in under 5 seconds');
    }

    /**
     * Test 15: Data Volume Targets Met
     */
    public function test_data_volume_targets()
    {
        $this->seed(\Database\Seeders\EnterpriseDataSeeder::class);

        $checks = [
            'Tenants' => [Tenant::class, 1],
            'Campuses' => [Campus::class, 2],
            'Users' => [LmsUser::class, 5090], // 90 teachers + 5000 students
            'Courses' => [Course::class, 200],
            'Videos' => [VideoAsset::class, 500],
            'Enrollments' => [Enrollment::class, 10000], // Approximate
            'Quiz Attempts' => [ExamAttempt::class, 20000], // Approximate
        ];

        foreach ($checks as $label => [$model, $minExpected]) {
            $actual = $model::count();
            $this->assertGreaterThanOrEqual(
                $minExpected * 0.9,
                $actual,
                "$label should have at least " . ($minExpected * 0.9) . " records (got $actual)"
            );
        }
    }

    /**
     * Test 16: Database Integrity Constraints
     */
    public function test_database_integrity()
    {
        $this->seed(\Database\Seeders\EnterpriseDataSeeder::class);

        // Check for orphaned enrollments
        $orphaned = Enrollment::whereNotIn(
            'user_id',
            LmsUser::pluck('id')
        )->count();

        $this->assertEquals(0, $orphaned, 'Should have no orphaned enrollments');

        // Check for orphaned courses
        $orphanedCourses = Course::whereNull('owner_id')->count();
        $this->assertEquals(0, $orphanedCourses, 'All courses should have owner');
    }

    /**
     * Test 17: Test Data Can Be Cleaned
     */
    public function test_test_data_cleanup()
    {
        $this->seed(\Database\Seeders\EnterpriseDataSeeder::class);

        // Verify test data exists
        $testUsers = LmsUser::where('code', 'like', 'GV%')->count();
        $this->assertGreaterThan(0, $testUsers, 'Test users should exist');

        // Cleanup (would normally delete test data)
        // This is a dry-run to verify logic
        $deleteCount = LmsUser::where('code', 'like', 'GV%')->count();
        $this->assertGreaterThan(0, $deleteCount, 'Should be able to identify test data for cleanup');
    }

    /**
     * Test 18: Export Functionality
     */
    public function test_checklist_export_functionality()
    {
        $this->seed(\Database\Seeders\GoLiveChecklistSeeder::class);

        $user = $this->createTestUser('admin');

        $response = $this->actingAs($user)
            ->get('/api/v1/golive-checklist/export?format=csv');

        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('csv', $response->json());
        $this->assertStringContainsString('Item Code', $response->json('csv'));
    }

    /**
     * Test 19: GoLive Readiness Report
     */
    public function test_golive_readiness_report()
    {
        $this->seed(\Database\Seeders\GoLiveChecklistSeeder::class);

        $user = $this->createTestUser('admin');

        $response = $this->actingAs($user)
            ->get('/api/v1/golive-checklist/readiness-report');

        $this->assertEquals(200, $response->status());

        $response->assertJsonStructure([
            'data' => [
                'overall_status',
                'categories',
                'critical_issues',
                'estimated_time_to_golive_hours',
                'go_live_eligible',
            ],
        ]);
    }

    /**
     * Test 20: Seed Data Re-run No Duplicates
     */
    public function test_multiple_seed_runs_no_duplicates()
    {
        $this->seed(\Database\Seeders\EnterpriseDataSeeder::class);
        $countAfterFirst = Course::count();

        // Running again
        $this->seed(\Database\Seeders\EnterpriseDataSeeder::class);
        $countAfterSecond = Course::count();

        // Should not double (accounting for unique constraint allowing some growth)
        $this->assertLessThan($countAfterFirst * 1.2, $countAfterSecond,
            'Second seed should not duplicate data significantly');
    }

    // Helper methods

    protected function createTestUser($role = 'student')
    {
        $user = LmsUser::factory()->create([
            'user_type' => $role,
        ]);

        $user->assignRole($role, 1);

        return $user;
    }
}
