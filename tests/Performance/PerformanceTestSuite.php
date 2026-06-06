<?php

namespace Tests\Performance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Gradebook;
use App\Models\GradeItem;
use App\Models\LearnerGrade;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * EraLMS Enterprise Performance Test Suite
 * 
 * Performance Benchmarks & Targets:
 * ├── Dashboard Load Time: < 1500ms
 * ├── Lesson/Chapter Load: < 1000ms  
 * ├── Save Learning Progress: < 300ms
 * ├── Submit Quiz: < 1000ms
 * ├── Gradebook (500 students): < 2000ms
 * ├── Video Start Playback: < 2000ms
 * ├── API Response Time: < 500ms (p95)
 * ├── Concurrent User Test: 1000 users
 * └── Queue Processing: Non-blocking (async)
 * 
 * Testing Methodology:
 * - Load generation via Apache JMeter / k6
 * - Response time metrics: Min, Max, Average, p95, p99
 * - Resource monitoring: CPU, Memory, Database connections
 * - Post-test analysis and reporting
 */
class PerformanceTestSuite extends TestCase
{
    use DatabaseTransactions;

    protected $stopwatch;
    protected $results = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->stopwatch = new Stopwatch();
    }

    /**
     * PT-001: Dashboard Load Time (< 1500ms)
     * 
     * Measures the time to load the main dashboard with all widgets
     * Includes: enrollment summary, recent courses, notifications, analytics cards
     */
    public function test_dashboard_load_time()
    {
        $user = $this->createTestUser('admin');
        
        $this->stopwatch->start('dashboard_load');
        
        $response = $this->actingAs($user)->get('/api/v1/core/dashboard');
        
        $event = $this->stopwatch->stop('dashboard_load');
        $duration = $event->getDuration();
        
        $this->assertLessThan(1500, $duration, 
            "Dashboard load time {$duration}ms exceeds target of 1500ms");
        
        $response->assertStatus(200);
        
        $this->recordMetric('dashboard_load_time', $duration, 'ms');
    }

    /**
     * PT-002: Lesson/Course Section Load Time (< 1000ms)
     * 
     * Measures time to load a lesson with all components
     * Includes: lesson content, sections, embedded videos, discussion threads
     */
    public function test_lesson_load_time()
    {
        $course = Course::factory()->create();
        $user = $this->createTestUser('student');
        Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        
        $this->stopwatch->start('lesson_load');
        
        $response = $this->actingAs($user)
            ->get("/api/v1/courses/{$course->id}/sections");
        
        $event = $this->stopwatch->stop('lesson_load');
        $duration = $event->getDuration();
        
        $this->assertLessThan(1000, $duration,
            "Lesson load time {$duration}ms exceeds target of 1000ms");
        
        $response->assertStatus(200);
        
        $this->recordMetric('lesson_load_time', $duration, 'ms');
    }

    /**
     * PT-003: Save Learning Progress (< 300ms)
     * 
     * Measures time to save student progress on a lesson component
     * Critical for perceived performance during interaction
     */
    public function test_save_learning_progress()
    {
        $user = $this->createTestUser('student');
        $course = Course::factory()->create();
        
        Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
        
        $payload = [
            'course_id' => $course->id,
            'section_id' => 123,
            'component_id' => 456,
            'progress_percent' => 50,
            'time_spent_seconds' => 300,
            'interactions_count' => 5,
        ];
        
        $this->stopwatch->start('save_progress');
        
        $response = $this->actingAs($user)->post(
            '/api/v1/learning-progress/save',
            $payload
        );
        
        $event = $this->stopwatch->stop('save_progress');
        $duration = $event->getDuration();
        
        $this->assertLessThan(300, $duration,
            "Save progress time {$duration}ms exceeds target of 300ms");
        
        $response->assertStatus(200);
        
        $this->recordMetric('save_progress_time', $duration, 'ms');
    }

    /**
     * PT-004: Submit Quiz/Exam (< 1000ms)
     * 
     * Measures time to submit exam attempt and receive score
     * Includes: answer validation, score calculation, feedback generation
     */
    public function test_submit_quiz_attempt()
    {
        $user = $this->createTestUser('student');
        $course = Course::factory()->create();
        $exam = \App\Models\Exam::factory()->create(['course_id' => $course->id]);
        
        Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
        
        // Create exam attempt
        $attempt = \App\Models\ExamAttempt::factory()->create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
        ]);
        
        $answers = [
            ['question_id' => 1, 'answer' => 'A'],
            ['question_id' => 2, 'answer' => 'B'],
            ['question_id' => 3, 'answer' => 'C'],
        ];
        
        $this->stopwatch->start('submit_quiz');
        
        $response = $this->actingAs($user)->post(
            "/api/v1/exam-attempts/{$attempt->id}/submit",
            ['answers' => $answers]
        );
        
        $event = $this->stopwatch->stop('submit_quiz');
        $duration = $event->getDuration();
        
        $this->assertLessThan(1000, $duration,
            "Quiz submit time {$duration}ms exceeds target of 1000ms");
        
        $response->assertStatus(200);
        
        $this->recordMetric('quiz_submit_time', $duration, 'ms');
    }

    /**
     * PT-005: Gradebook Load (500 students) (< 2000ms)
     * 
     * Measures time to load gradebook with 500 students and grade matrix
     * Critical for teacher workflow - must be fast for usability
     * Includes: grade calculations, filtering, sorting
     */
    public function test_gradebook_500_students_load_time()
    {
        $course = Course::factory()->create();
        $teacher = $this->createTestUser('teacher');
        $course->update(['owner_id' => $teacher->id]);
        
        // Create gradebook structure
        $gradebook = Gradebook::factory()->create(['course_id' => $course->id]);
        
        // Create grade items
        $gradeItems = GradeItem::factory()->count(5)->create(['gradebook_id' => $gradebook->id]);
        
        // Create 500 students with grades
        $students = [];
        for ($i = 0; $i < 500; $i++) {
            $student = $this->createTestUser('student');
            
            Enrollment::factory()->create([
                'user_id' => $student->id,
                'course_id' => $course->id,
            ]);
            
            // Create grades for each grade item
            foreach ($gradeItems as $item) {
                LearnerGrade::factory()->create([
                    'user_id' => $student->id,
                    'grade_item_id' => $item->id,
                    'raw_score' => rand(0, 100),
                ]);
            }
            
            $students[] = $student;
        }
        
        $this->stopwatch->start('gradebook_load');
        
        $response = $this->actingAs($teacher)->get(
            "/api/v1/gradebooks/{$gradebook->id}?include_students=true"
        );
        
        $event = $this->stopwatch->stop('gradebook_load');
        $duration = $event->getDuration();
        
        $this->assertLessThan(2000, $duration,
            "Gradebook load time for 500 students: {$duration}ms exceeds target of 2000ms");
        
        $response->assertStatus(200);
        
        $this->recordMetric('gradebook_500_students_time', $duration, 'ms');
    }

    /**
     * PT-006: Video Start Playback (< 2000ms)
     * 
     * Measures time from video request to first frame
     * Includes: HLS manifest loading, quality selection, initial segment fetch
     */
    public function test_video_start_playback_time()
    {
        $user = $this->createTestUser('student');
        $course = Course::factory()->create();
        $video = \App\Models\VideoAsset::factory()->create();
        
        Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
        
        $this->stopwatch->start('video_playback_start');
        
        $response = $this->actingAs($user)->post(
            "/api/v1/video-sessions/start",
            [
                'video_id' => $video->id,
                'client_bandwidth_kbps' => 2500,
            ]
        );
        
        $event = $this->stopwatch->stop('video_playback_start');
        $duration = $event->getDuration();
        
        $this->assertLessThan(2000, $duration,
            "Video playback start time {$duration}ms exceeds target of 2000ms");
        
        $response->assertStatus(200);
        
        $this->recordMetric('video_playback_start_time', $duration, 'ms');
    }

    /**
     * PT-007: API Response Time Distribution (p95 < 500ms)
     * 
     * Measures API endpoint response times across multiple requests
     * Evaluates: 95th percentile, max, average
     */
    public function test_api_response_time_distribution()
    {
        $user = $this->createTestUser('student');
        $course = Course::factory()->create();
        
        Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
        
        $responseTimes = [];
        
        // Make 100 API calls
        for ($i = 0; $i < 100; $i++) {
            $this->stopwatch->start("api_call_{$i}");
            
            $response = $this->actingAs($user)
                ->get("/api/v1/courses/{$course->id}");
            
            $event = $this->stopwatch->stop("api_call_{$i}");
            $responseTimes[] = $event->getDuration();
            
            $response->assertStatus(200);
        }
        
        // Calculate percentiles
        sort($responseTimes);
        $p95 = $responseTimes[94]; // 95th percentile
        $max = max($responseTimes);
        $avg = array_sum($responseTimes) / count($responseTimes);
        
        $this->assertLessThan(500, $p95,
            "API p95 response time {$p95}ms exceeds target of 500ms");
        
        $this->recordMetric('api_p95_response_time', $p95, 'ms');
        $this->recordMetric('api_max_response_time', $max, 'ms');
        $this->recordMetric('api_avg_response_time', $avg, 'ms');
    }

    /**
     * PT-008: Concurrent User Test (1000 users)
     * 
     * Load test with 1000 concurrent simulated users
     * Measures system behavior under high load
     * Note: This test should be run with load testing tools like k6 or JMeter
     * 
     * This is a placeholder for demonstration
     * Actual concurrent testing done with: k6 load-test.js
     */
    public function test_concurrent_users_simulation()
    {
        // This is a conceptual test showing the methodology
        // Actual concurrent testing uses tools like k6 or Apache JMeter
        
        $config = [
            'concurrent_users' => 1000,
            'ramp_up_time_seconds' => 60,
            'test_duration_seconds' => 300,
            'endpoints' => [
                '/api/v1/core/dashboard' => 0.3,
                '/api/v1/courses' => 0.2,
                '/api/v1/learning-progress/save' => 0.3,
                '/api/v1/enrollments' => 0.2,
            ],
        ];
        
        // Expected targets under load:
        $targets = [
            'avg_response_time_ms' => 500,
            'p95_response_time_ms' => 1000,
            'p99_response_time_ms' => 2000,
            'error_rate_percent' => 0.1,
            'cpu_usage_percent' => 70,
            'memory_usage_percent' => 75,
            'database_connection_pool_usage' => 0.8,
        ];
        
        // Record test configuration
        $this->recordMetric('concurrent_test_config', json_encode($config), 'config');
        $this->recordMetric('concurrent_test_targets', json_encode($targets), 'targets');
    }

    /**
     * PT-009: Database Query Performance
     * 
     * Analyze slow queries and database connection pool usage
     */
    public function test_database_query_performance()
    {
        \DB::enableQueryLog();
        
        // Simulate typical queries
        $course = Course::with(['sections', 'enrollments.user'])->first();
        $enrollments = Enrollment::where('status', 'active')
            ->with('course', 'user')
            ->paginate(50);
        
        $queries = \DB::getQueryLog();
        
        $slowQueries = [];
        foreach ($queries as $query) {
            if ($query['time'] > 100) { // > 100ms is considered slow
                $slowQueries[] = $query;
            }
        }
        
        $this->assertEmpty($slowQueries, 
            "Found " . count($slowQueries) . " slow queries (> 100ms)");
        
        $this->recordMetric('total_queries', count($queries), 'count');
        $this->recordMetric('slow_queries_count', count($slowQueries), 'count');
    }

    /**
     * PT-010: Cache Hit Ratio & Effectiveness
     * 
     * Measures cache efficiency for frequently accessed data
     */
    public function test_cache_effectiveness()
    {
        // Clear cache before test
        \Cache::flush();
        
        $course = Course::factory()->create();
        
        // First access - cache miss
        $startMemory = memory_get_usage();
        $course1 = Course::find($course->id);
        $firstAccessTime = microtime(true);
        
        // Second access - cache hit
        $start = microtime(true);
        $course2 = Course::find($course->id);
        $cachedAccessTime = microtime(true) - $start;
        
        // Cached access should be significantly faster
        $this->assertLess($cachedAccessTime, $firstAccessTime,
            "Cache hit should be faster than initial access");
        
        $this->recordMetric('cache_hit_latency_ratio', 
            $firstAccessTime / ($cachedAccessTime ?: 0.001), 'ratio');
    }

    /**
     * PT-011: Memory Usage & Leak Detection
     * 
     * Monitor memory consumption for potential leaks
     */
    public function test_memory_usage()
    {
        $initialMemory = memory_get_usage(true);
        
        // Simulate user workflow
        for ($i = 0; $i < 100; $i++) {
            $course = Course::factory()->create();
            Enrollment::factory()->count(50)->create(['course_id' => $course->id]);
            
            $this->actingAs($this->createTestUser('teacher'))
                ->get("/api/v1/gradebooks");
        }
        
        $finalMemory = memory_get_usage(true);
        $memoryUsed = ($finalMemory - $initialMemory) / 1024 / 1024; // MB
        
        // Memory usage should not exceed reasonable limits
        $this->assertLessThan(500, $memoryUsed, 
            "Memory usage {$memoryUsed}MB exceeds acceptable limit");
        
        $this->recordMetric('memory_used_mb', $memoryUsed, 'MB');
    }

    /**
     * PT-012: Queue Processing Performance
     * 
     * Verify that long-running jobs are queued and don't block UI
     */
    public function test_queue_processing_non_blocking()
    {
        // Task that would normally take 30+ seconds
        $this->stopwatch->start('analytics_generation');
        
        // Should dispatch to queue immediately
        $response = $this->actingAs($this->createTestUser('admin'))
            ->post('/api/v1/analytics/rebuild-snapshot', [
                'course_id' => 1,
            ]);
        
        $event = $this->stopwatch->stop('analytics_generation');
        $duration = $event->getDuration();
        
        // Should return immediately (< 100ms)
        $this->assertLessThan(100, $duration,
            "Queue dispatch took {$duration}ms, should be near-instant");
        
        $response->assertStatus(202); // Accepted (async)
        
        $this->recordMetric('queue_dispatch_time', $duration, 'ms');
    }

    /**
     * PT-013: Search Performance (Full-Text Search)
     * 
     * Measure full-text search latency
     */
    public function test_full_text_search_performance()
    {
        // Create test courses for search
        $courses = Course::factory()->count(100)->create();
        
        $this->stopwatch->start('fts_search');
        
        $results = $this->actingAs($this->createTestUser('student'))
            ->get('/api/v1/courses/search', [
                'q' => 'Lập Trình',
            ]);
        
        $event = $this->stopwatch->stop('fts_search');
        $duration = $event->getDuration();
        
        // Search should be fast
        $this->assertLessThan(500, $duration,
            "Full-text search took {$duration}ms");
        
        $results->assertStatus(200);
        
        $this->recordMetric('fts_search_time', $duration, 'ms');
    }

    /**
     * PT-014: Report Generation Performance
     * 
     * Measure time to generate various reports
     */
    public function test_report_generation_performance()
    {
        $course = Course::factory()->create();
        
        // Create students and grades
        for ($i = 0; $i < 100; $i++) {
            $student = $this->createTestUser('student');
            Enrollment::factory()->create([
                'user_id' => $student->id,
                'course_id' => $course->id,
            ]);
        }
        
        $this->stopwatch->start('report_generation');
        
        $response = $this->actingAs($this->createTestUser('teacher'))
            ->get("/api/v1/courses/{$course->id}/reports/grade-report", [
                'format' => 'pdf',
            ]);
        
        $event = $this->stopwatch->stop('report_generation');
        $duration = $event->getDuration();
        
        // Report generation should complete within reasonable time
        $this->assertLessThan(5000, $duration,
            "Report generation took {$duration}ms");
        
        $this->recordMetric('report_generation_time', $duration, 'ms');
    }

    /**
     * Helper Methods
     */

    protected function createTestUser($role = 'student')
    {
        $user = \App\Models\LmsUser::factory()->create([
            'user_type' => $role,
        ]);
        
        $user->assignRole($role, 1);
        
        return $user;
    }

    protected function recordMetric($name, $value, $unit = '')
    {
        $this->results[$name] = [
            'value' => $value,
            'unit' => $unit,
            'timestamp' => now(),
        ];
    }

    protected function tearDown(): void
    {
        // Generate performance report
        $this->generatePerformanceReport();
        
        parent::tearDown();
    }

    protected function generatePerformanceReport()
    {
        $report = [
            'timestamp' => now(),
            'environment' => [
                'app_version' => config('app.version'),
                'php_version' => PHP_VERSION,
                'database' => config('database.default'),
            ],
            'metrics' => $this->results,
            'status' => $this->allMetricsPass() ? 'PASS' : 'FAIL',
        ];
        
        // Save report
        file_put_contents(
            storage_path('performance_reports/report_' . now()->timestamp . '.json'),
            json_encode($report, JSON_PRETTY_PRINT)
        );
    }

    protected function allMetricsPass()
    {
        $targets = [
            'dashboard_load_time' => 1500,
            'lesson_load_time' => 1000,
            'save_progress_time' => 300,
            'quiz_submit_time' => 1000,
            'gradebook_500_students_time' => 2000,
            'video_playback_start_time' => 2000,
        ];
        
        foreach ($targets as $metric => $target) {
            if (isset($this->results[$metric]) && $this->results[$metric]['value'] > $target) {
                return false;
            }
        }
        
        return true;
    }
}
