<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Campus;
use App\Models\AcademicUnit;
use App\Models\LmsUser;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CourseComponent;
use App\Models\VideoAsset;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Exam;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\AttendanceSession;
use App\Models\AttendanceRecord;
use App\Models\ExamAttempt;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Models\Gradebook;
use App\Models\GradeItem;
use App\Models\UserCourseProgress;
use App\Models\LearningCompletion;
use App\Models\DiscussionThread;
use App\Models\DiscussionPost;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use Illuminate\Database\Seeder;
use Faker\Factory;
use Illuminate\Support\Facades\Log;

/**
 * Enterprise Data Seeder - Production Grade
 * 
 * High-performance seeder generating:
 * - 1 Tenant (VABIS LMS)
 * - 2 Campuses
 * - 19 Academic Units
 * - 90 Teachers
 * - 5,000 Students
 * - 200 Courses
 */
class EnterpriseDataSeeder extends Seeder
{
    protected $faker;
    protected $tenant;
    protected $startTime;
    protected $batchSize = 500;

    public function run(): void
    {
        $this->startTime = now();
        $this->faker = Factory::create('vi_VN');

        $this->disableForeignKeyChecks();

        try {
            echo "\n🌱 Starting Enterprise Data Seeding...\n";

            $this->seedTenant();
            $this->seedCampuses();
            $this->seedAcademicUnits();
            $this->seedTeachers();
            $this->seedStudents();
            $this->seedCourses();

            echo "\n✅ Enterprise Data Seeding completed!\n";
            $this->logSummary();

        } catch (\Exception $e) {
            echo "\n❌ Seeding failed: {$e->getMessage()}\n";
            Log::error('Seeder error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;

        } finally {
            $this->enableForeignKeyChecks();
        }
    }

    protected function seedTenant(): void
    {
        echo "📍 Creating Tenant...\n";

        $this->tenant = Tenant::updateOrCreate(
            ['code' => 'VABIS'],
            [
                'name' => 'VABIS LMS Enterprise',
                'legal_name' => 'VABIS Education Corporation',
                'domain' => 'lms.vabis.edu.vn',
                'status' => 'active',
                'logo_url' => 'https://vabis.edu.vn/logo.png',
                'primary_color' => '#1f2937',
                'secondary_color' => '#3b82f6',
                'locale' => 'vi',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'settings' => [
                    'max_users' => 10000,
                    'max_courses' => 500,
                ],
            ]
        );

        echo "  ✓ Tenant created/updated: {$this->tenant->name}\n";
    }

    protected function seedCampuses(): void
    {
        echo "📍 Creating 2 Campuses...\n";

        $campuses = [
            [
                'code' => 'HN001',
                'name' => 'Cơ sở chính - Hà Nội',
                'address' => '123 Đường Láng, Quận Đống Đa, Hà Nội',
                'phone' => '024-3xxx-xxxx',
                'email' => 'hanoi@vabis.edu.vn',
                'status' => 'active',
            ],
            [
                'code' => 'HCM001',
                'name' => 'Cơ sở TP.HCM',
                'address' => '456 Nguyễn Hữu Cảnh, Quận Bình Thạnh, TP.HCM',
                'phone' => '028-3xxx-xxxx',
                'email' => 'hochiminh@vabis.edu.vn',
                'status' => 'active',
            ],
        ];

        foreach ($campuses as $data) {
            Campus::create(array_merge($data, [
                'tenant_id' => $this->tenant->id,
            ]));
            echo "  ✓ Campus created: {$data['name']}\n";
        }
    }

    protected function seedAcademicUnits(): void
    {
        echo "📍 Creating 19 Academic Units...\n";

        $units = [
            ['code' => 'KHGD', 'name' => 'Khoa Giáo Dục', 'type' => 'faculty'],
            ['code' => 'KHKT', 'name' => 'Khoa Kỹ Thuật Công Nghệ', 'type' => 'faculty'],
            ['code' => 'KHKD', 'name' => 'Khoa Kinh Doanh & Quản Lý', 'type' => 'faculty'],
            ['code' => 'KHSK', 'name' => 'Khoa Sức Khỏe', 'type' => 'faculty'],
            ['code' => 'KHCNTT', 'name' => 'Khoa Công Nghệ Thông Tin', 'type' => 'faculty'],
            ['code' => 'KHKTXD', 'name' => 'Khoa Kỹ Thuật Xây Dựng', 'type' => 'faculty'],
            ['code' => 'KHKE', 'name' => 'Khoa Kinh Tế', 'type' => 'faculty'],
            ['code' => 'TTNGOẠI', 'name' => 'Trung Tâm Ngoại Ngữ', 'type' => 'center'],
            ['code' => 'TTDN', 'name' => 'Trung Tâm Đào Tạo Doanh Nghiệp', 'type' => 'center'],
            ['code' => 'BMTOÁN', 'name' => 'Bộ Môn Toán', 'type' => 'department'],
            ['code' => 'BMLÝ', 'name' => 'Bộ Môn Vật Lý', 'type' => 'department'],
            ['code' => 'BMHÓA', 'name' => 'Bộ Môn Hóa', 'type' => 'department'],
            ['code' => 'BMSINH', 'name' => 'Bộ Môn Sinh', 'type' => 'department'],
            ['code' => 'BMVĂN', 'name' => 'Bộ Môn Văn', 'type' => 'department'],
            ['code' => 'BMSỬ', 'name' => 'Bộ Môn Sử', 'type' => 'department'],
            ['code' => 'BMĐL', 'name' => 'Bộ Môn Địa Lý', 'type' => 'department'],
            ['code' => 'BMTHỂ', 'name' => 'Bộ Môn Thể Dục', 'type' => 'department'],
            ['code' => 'BMÂM', 'name' => 'Bộ Môn Âm Nhạc', 'type' => 'department'],
            ['code' => 'BMMỸ', 'name' => 'Bộ Môn Mỹ Thuật', 'type' => 'department'],
        ];

        foreach ($units as $index => $data) {
            AcademicUnit::create(array_merge($data, [
                'tenant_id' => $this->tenant->id,
                'status' => 'active',
            ]));
            
            if (($index + 1) % 5 === 0) {
                echo "  ✓ Created " . ($index + 1) . " academic units\n";
            }
        }
    }

    protected function seedTeachers(): void
    {
        echo "📍 Creating 90 Teachers...\n";

        $maleNames = ['Nguyễn Văn', 'Trần Quốc', 'Lê Minh', 'Phạm Văn', 'Hoàng Minh', 'Phan Văn', 'Vũ Quang', 'Đặng Minh', 'Bùi Anh', 'Tô Minh'];
        $femaleNames = ['Nguyễn Thị', 'Trần Thị', 'Lê Thị', 'Phạm Thị', 'Hoàng Thị', 'Phan Thị', 'Vũ Thị', 'Đặng Thị', 'Bùi Thị', 'Tô Thị'];
        $lastNames = ['Minh', 'Nam', 'Hùng', 'Lan', 'Dung', 'Long', 'Tú', 'Hồng', 'Thanh', 'Nhật', 'Anh', 'Linh'];

        for ($i = 1; $i <= 90; $i++) {
            $isMale = $i % 2 === 0;
            $prefix = $isMale ? $maleNames[$i % count($maleNames)] : $femaleNames[$i % count($femaleNames)];
            $last = $lastNames[$i % count($lastNames)];
            $fullName = "$prefix $last";

            LmsUser::create([
                'tenant_id' => $this->tenant->id,
                'code' => 'GV' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'full_name' => $fullName,
                'email' => 'gv' . str_pad($i, 4, '0', STR_PAD_LEFT) . '@vabis.edu.vn',
                'phone' => '0' . rand(3, 9) . sprintf('%07d', rand(0, 9999999)),
                'user_type' => 'teacher',
                'status' => 'active',
                'avatar_url' => "https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($fullName),
                'metadata' => [
                    'gender' => $isMale ? 'male' : 'female',
                    'title' => 'PGS.TS.',
                    'bio' => "Giảng viên: $fullName",
                ],
            ]);

            if ($i % 30 === 0) {
                echo "  ✓ Created $i teachers\n";
            }
        }
    }

    protected function seedStudents(): void
    {
        echo "📍 Creating 5,000 Students...\n";

        $maleNames = ['Nguyễn Văn', 'Trần Quốc', 'Lê Minh', 'Phạm Văn', 'Hoàng Minh', 'Phan Văn', 'Vũ Quang', 'Đặng Minh', 'Bùi Anh', 'Tô Minh'];
        $femaleNames = ['Nguyễn Thị', 'Trần Thị', 'Lê Thị', 'Phạm Thị', 'Hoàng Thị', 'Phan Thị', 'Vũ Thị', 'Đặng Thị', 'Bùi Thị', 'Tô Thị'];
        $lastNames = ['Minh', 'Nam', 'Hùng', 'Lan', 'Dung', 'Long', 'Tú', 'Hồng', 'Thanh', 'Nhật', 'Anh', 'Linh'];

        $batch = [];
        for ($i = 1; $i <= 5000; $i++) {
            $isMale = $i % 2 === 0;
            $prefix = $isMale ? $maleNames[$i % count($maleNames)] : $femaleNames[$i % count($femaleNames)];
            $last = $lastNames[$i % count($lastNames)];
            $fullName = "$prefix $last";

            $batch[] = [
                'tenant_id' => $this->tenant->id,
                'code' => 'SV' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'full_name' => $fullName,
                'email' => 'sv' . str_pad($i, 5, '0', STR_PAD_LEFT) . '@student.vabis.edu.vn',
                'phone' => '0' . rand(3, 9) . sprintf('%07d', rand(0, 9999999)),
                'user_type' => 'student',
                'status' => 'active',
                'avatar_url' => "https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($fullName),
                'metadata' => [
                    'gender' => $isMale ? 'male' : 'female',
                    'student_id' => 'STU' . str_pad($i, 6, '0', STR_PAD_LEFT),
                    'enrollment_date' => now()->subMonths(rand(1, 12))->toDateString(),
                ],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Batch insert
            if (count($batch) >= $this->batchSize) {
                LmsUser::insert($batch);
                $batch = [];
            }

            if ($i % 500 === 0) {
                echo "  ✓ Created $i students\n";
            }
        }

        // Insert remaining batch
        if (!empty($batch)) {
            LmsUser::insert($batch);
        }
    }

    protected function seedCourses(): void
    {
        echo "📍 Creating 200 Courses...\n";

        $courseTypes = ['theory', 'practical', 'online', 'hybrid'];
        $courseNames = [
            'Lập Trình Web', 'Cơ Sở Dữ Liệu', 'Mạng Máy Tính',
            'Toán Cao Cấp', 'Lý Thuyết Xác Suất', 'Giải Tích',
            'Vật Lý Đại Cương', 'Tiếng Anh 1', 'Kinh Tế Vi Mô',
            'Marketing', 'Thiết Kế Đồ Họa', 'Quản Lý Dự Án',
        ];

        $teachers = LmsUser::where('tenant_id', $this->tenant->id)
            ->where('user_type', 'teacher')
            ->pluck('id')
            ->toArray();

        $units = AcademicUnit::where('tenant_id', $this->tenant->id)
            ->pluck('id')
            ->toArray();

        $batch = [];
        for ($i = 1; $i <= 200; $i++) {
            $courseName = $courseNames[$i % count($courseNames)];
            
            $batch[] = [
                'tenant_id' => $this->tenant->id,
                'code' => 'CO' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'title' => $courseName . ' - Lớp ' . chr(65 + (($i - 1) % 26)),
                'short_description' => $courseName,
                'description' => 'Khóa học: ' . $courseName,
                'course_type' => $courseTypes[$i % count($courseTypes)],
                'status' => 'active',
                'visibility' => 'public',
                'level' => ['beginner', 'intermediate', 'advanced'][($i - 1) % 3],
                'language' => 'vi',
                'owner_id' => $teachers[$i % count($teachers)],
                'academic_unit_id' => $units[$i % count($units)],
                'estimated_hours' => rand(20, 100),
                'settings' => [
                    'max_students' => 100,
                    'allow_self_enrollment' => true,
                ],
                'published_at' => now()->subMonths(rand(1, 12)),
                'created_at' => now()->subMonths(rand(1, 12)),
                'updated_at' => now(),
            ];

            if (count($batch) >= $this->batchSize) {
                Course::insert($batch);
                $batch = [];
            }

            if ($i % 50 === 0) {
                echo "  ✓ Created $i courses\n";
            }
        }

        if (!empty($batch)) {
            Course::insert($batch);
        }
    }

    protected function logSummary(): void
    {
        $duration = now()->diffInSeconds($this->startTime);
        $tenantCount = Tenant::count();
        $userCount = LmsUser::count();
        $courseCount = Course::count();

        echo "\n📊 Seeding Summary:\n";
        echo "   Duration: {$duration}s\n";
        echo "   Tenants: $tenantCount\n";
        echo "   Users: $userCount\n";
        echo "   Courses: $courseCount\n\n";
    }

    protected function seedLessons(): void
    {
        echo "📍 Creating 1000 Lessons (as course sections)...\n";
        
        $lessonCounter = 0;
        $lessonsPerCourse = 5;
        
        foreach ($this->courses as $courseIndex => $course) {
            for ($sectionNum = 1; $sectionNum <= $lessonsPerCourse; $sectionNum++) {
                $section = CourseSection::create([
                    'tenant_id' => $this->tenant->id,
                    'course_id' => $course->id,
                    'code' => 'LS' . str_pad(++$lessonCounter, 5, '0', STR_PAD_LEFT),
                    'title' => "Bài học $sectionNum: " . $this->faker->word(),
                    'description' => $this->faker->paragraph(),
                    'type' => 'lesson',
                    'sequence' => $sectionNum,
                    'status' => 'active',
                    'release_at' => now()->subMonths(rand(0, 6)),
                    'due_at' => now()->addMonths(rand(1, 6)),
                ]);
                
                // Add some lesson components (video, text, quiz)
                for ($compNum = 1; $compNum <= 3; $compNum++) {
                    CourseComponent::create([
                        'tenant_id' => $this->tenant->id,
                        'section_id' => $section->id,
                        'component_type' => ['video', 'text', 'quiz'][$compNum - 1],
                        'sequence' => $compNum,
                        'required' => true,
                    ]);
                }
            }
            
            if (($courseIndex + 1) % 20 === 0) {
                echo "  ✓ Created lessons for " . ($courseIndex + 1) . " courses\n";
            }
        }
    }

    protected function seedVideos(): void
    {
        echo "📍 Creating 500 Video Assets...\n";
        
        for ($i = 1; $i <= 500; $i++) {
            $video = VideoAsset::create([
                'tenant_id' => $this->tenant->id,
                'code' => 'VA' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'title' => 'Video: ' . $this->faker->word(),
                'description' => $this->faker->paragraph(),
                'duration_seconds' => rand(300, 3600),
                'file_size' => rand(100, 500) * 1024 * 1024,
                'hls_url' => "https://cdn.vabis.edu.vn/videos/va" . str_pad($i, 5, '0', STR_PAD_LEFT) . "/stream.m3u8",
                'thumbnail_url' => "https://via.placeholder.com/320x180?text=Video+$i",
                'transcript_url' => "https://cdn.vabis.edu.vn/videos/va" . str_pad($i, 5, '0', STR_PAD_LEFT) . "/transcript.vtt",
                'status' => 'published',
                'published_at' => now()->subMonths(rand(0, 12)),
            ]);
            $this->videoAssets[] = $video;
            
            if ($i % 100 === 0) {
                echo "  ✓ Created $i videos\n";
            }
        }
    }

    protected function seedQuestions(): void
    {
        echo "📍 Creating Questions & Question Banks...\n";
        
        // Create 5 question banks
        $questionBanks = [];
        for ($i = 1; $i <= 5; $i++) {
            $qb = QuestionBank::create([
                'tenant_id' => $this->tenant->id,
                'code' => 'QB' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'name' => 'Ngân Hàng Câu Hỏi ' . $i,
                'description' => 'Question bank ' . $i,
            ]);
            $questionBanks[] = $qb;
        }
        
        // Create 1000 questions
        $questionTypes = ['multiple_choice', 'true_false', 'short_answer', 'essay', 'matching'];
        $bloomLevels = ['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'];
        $difficulties = ['easy', 'medium', 'hard'];
        
        for ($i = 1; $i <= 1000; $i++) {
            $question = Question::create([
                'tenant_id' => $this->tenant->id,
                'question_bank_id' => $questionBanks[$i % count($questionBanks)]->id,
                'code' => 'Q' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'stem' => 'Câu hỏi: ' . $this->faker->word() . '?',
                'question_type' => $questionTypes[$i % count($questionTypes)],
                'difficulty_level' => $difficulties[$i % count($difficulties)],
                'bloom_level' => $bloomLevels[$i % count($bloomLevels)],
                'suggested_time_seconds' => rand(30, 300),
                'feedback' => $this->faker->paragraph(),
                'status' => 'active',
                'approved_at' => now(),
            ]);
            
            // Add options for multiple choice
            if (in_array($question->question_type, ['multiple_choice', 'true_false'])) {
                for ($opt = 1; $opt <= 4; $opt++) {
                    \App\Models\QuestionOption::create([
                        'question_id' => $question->id,
                        'option_text' => 'Tùy chọn ' . $opt,
                        'sort_order' => $opt,
                        'is_correct' => $opt === 1,
                    ]);
                }
            }
            
            if ($i % 200 === 0) {
                echo "  ✓ Created $i questions\n";
            }
        }
    }

    protected function seedExams(): void
    {
        echo "📍 Creating 100 Exams...\n";
        
        for ($i = 1; $i <= 100; $i++) {
            $course = $this->courses[$i % count($this->courses)];
            $teacher = $this->teachers[$i % count($this->teachers)];
            
            $exam = Exam::create([
                'tenant_id' => $this->tenant->id,
                'course_id' => $course->id,
                'created_by' => $teacher->id,
                'code' => 'EX' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'title' => 'Bài kiểm tra ' . $i,
                'description' => 'Exam ' . $i,
                'total_score' => 100,
                'pass_score' => 50,
                'duration_minutes' => rand(30, 180),
                'num_questions' => rand(10, 50),
                'show_result_mode' => 'after_submission',
                'allow_review' => true,
                'allow_multiple_attempts' => true,
                'max_attempts' => 3,
                'status' => 'active',
            ]);
            
            if ($i % 20 === 0) {
                echo "  ✓ Created $i exams\n";
            }
        }
    }

    protected function seedAssignments(): void
    {
        echo "📍 Creating 50 Assignments...\n";
        
        for ($i = 1; $i <= 50; $i++) {
            $course = $this->courses[$i % count($this->courses)];
            $teacher = $this->teachers[$i % count($this->teachers)];
            
            $assignment = Assignment::create([
                'tenant_id' => $this->tenant->id,
                'course_id' => $course->id,
                'created_by' => $teacher->id,
                'code' => 'AS' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'title' => 'Bài tập ' . $i,
                'description' => $this->faker->paragraph(),
                'due_at' => now()->addMonths(rand(1, 3)),
                'max_submissions' => 3,
                'max_score' => 100,
                'status' => 'active',
            ]);
            
            if ($i % 10 === 0) {
                echo "  ✓ Created $i assignments\n";
            }
        }
    }

    protected function seedEnrollments(): void
    {
        echo "📍 Creating 10000 Enrollments...\n";
        
        $enrollmentStatuses = ['active', 'completed', 'suspended', 'dropped'];
        
        $enrollmentCount = 0;
        foreach ($this->courses as $courseIndex => $course) {
            // 50-100 students per course
            $studentCount = rand(50, 100);
            $availableStudents = array_slice($this->students, 0, min($studentCount * 2, count($this->students)));
            $selectedStudents = array_slice($availableStudents, 0, $studentCount);
            
            foreach ($selectedStudents as $student) {
                Enrollment::create([
                    'tenant_id' => $this->tenant->id,
                    'course_id' => $course->id,
                    'user_id' => $student->id,
                    'status' => $enrollmentStatuses[$enrollmentCount % count($enrollmentStatuses)],
                    'enrolled_at' => now()->subMonths(rand(1, 12)),
                    'completion_percent' => rand(0, 100),
                    'risk_score' => rand(0, 100),
                ]);
                
                $enrollmentCount++;
                if ($enrollmentCount % 1000 === 0) {
                    echo "  ✓ Created $enrollmentCount enrollments\n";
                }
            }
        }
    }

    protected function seedAttendance(): void
    {
        echo "📍 Creating 5000 Attendance Records...\n";
        
        for ($i = 1; $i <= 500; $i++) {
            $session = AttendanceSession::create([
                'tenant_id' => $this->tenant->id,
                'course_id' => $this->courses[$i % count($this->courses)]->id,
                'session_date' => now()->subDays(rand(1, 60)),
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'status' => 'completed',
            ]);
            
            // Add 10 attendance records per session
            for ($j = 1; $j <= 10; $j++) {
                AttendanceRecord::create([
                    'tenant_id' => $this->tenant->id,
                    'session_id' => $session->id,
                    'user_id' => $this->students[($i * $j) % count($this->students)]->id,
                    'status' => rand(1, 10) > 2 ? 'present' : 'absent',
                    'checkin_at' => $session->start_time ? now()->subDays($i)->setTimeFromTimeString($session->start_time) : null,
                    'attended_minutes' => rand(0, 120),
                ]);
            }
            
            if ($i % 100 === 0) {
                echo "  ✓ Created attendance for $i sessions\n";
            }
        }
    }

    protected function seedQuizAttempts(): void
    {
        echo "📍 Creating 20000 Quiz Attempts...\n";
        
        $exams = Exam::inRandomOrder()->limit(100)->get();
        $attemptCount = 0;
        
        foreach ($exams as $exam) {
            $studentSample = array_slice($this->students, 0, rand(50, 200));
            
            foreach ($studentSample as $student) {
                for ($attempt = 1; $attempt <= rand(1, 3); $attempt++) {
                    $examAttempt = ExamAttempt::create([
                        'tenant_id' => $this->tenant->id,
                        'exam_id' => $exam->id,
                        'user_id' => $student->id,
                        'attempt_number' => $attempt,
                        'started_at' => now()->subDays(rand(1, 60)),
                        'submitted_at' => now()->subDays(rand(1, 60)),
                        'raw_score' => rand(0, 100),
                        'final_score' => rand(0, 100),
                        'pass_status' => rand(0, 100) > 40 ? 'pass' : 'fail',
                        'status' => 'completed',
                    ]);
                    
                    $attemptCount++;
                    if ($attemptCount % 5000 === 0) {
                        echo "  ✓ Created $attemptCount quiz attempts\n";
                    }
                }
            }
        }
    }

    protected function seedAssignmentSubmissions(): void
    {
        echo "📍 Creating 10000 Assignment Submissions...\n";
        
        $assignments = Assignment::inRandomOrder()->limit(50)->get();
        $submissionCount = 0;
        
        foreach ($assignments as $assignment) {
            $studentSample = array_slice($this->students, 0, rand(100, 300));
            
            foreach ($studentSample as $student) {
                AssignmentSubmission::create([
                    'tenant_id' => $this->tenant->id,
                    'assignment_id' => $assignment->id,
                    'user_id' => $student->id,
                    'submission_date' => now()->subDays(rand(1, 60)),
                    'file_name' => "submission_$student->id.pdf",
                    'file_url' => "https://storage.vabis.edu.vn/submissions/submission_$student->id.pdf",
                    'status' => rand(1, 3) > 1 ? 'submitted' : 'draft',
                    'submission_attempt' => rand(1, $assignment->max_submissions),
                ]);
                
                $submissionCount++;
                if ($submissionCount % 2500 === 0) {
                    echo "  ✓ Created $submissionCount assignment submissions\n";
                }
            }
        }
    }

    protected function seedCertificates(): void
    {
        echo "📍 Creating 1000 Certificates & Certificate Issues...\n";
        
        // Create 20 certificate templates
        $templates = [];
        for ($i = 1; $i <= 20; $i++) {
            $cert = Certificate::create([
                'tenant_id' => $this->tenant->id,
                'code' => 'CT' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'name' => 'Chứng chỉ hoàn thành khóa học ' . $i,
                'description' => 'Certificate ' . $i,
                'credential_type' => 'completion_certificate',
                'issuer_name' => 'VABIS LMS',
                'status' => 'active',
            ]);
            $templates[] = $cert;
        }
        
        // Create 1000 certificate issues
        $issueCount = 0;
        for ($i = 1; $i <= 1000; $i++) {
            $student = $this->students[$i % count($this->students)];
            
            CertificateIssue::create([
                'tenant_id' => $this->tenant->id,
                'certificate_id' => $templates[$i % count($templates)]->id,
                'recipient_id' => $student->id,
                'code' => 'CI' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'issued_date' => now()->subDays(rand(1, 365)),
                'valid_until' => now()->addYears(3),
                'status' => 'issued',
            ]);
            
            $issueCount++;
            if ($issueCount % 250 === 0) {
                echo "  ✓ Created $issueCount certificate issues\n";
            }
        }
    }

    protected function seedGradebooks(): void
    {
        echo "📍 Creating 20 Gradebooks...\n";
        
        $coursesSample = array_slice($this->courses, 0, 20);
        
        foreach ($coursesSample as $course) {
            $gradebook = Gradebook::create([
                'tenant_id' => $this->tenant->id,
                'course_id' => $course->id,
                'code' => 'GB' . str_pad($course->id, 5, '0', STR_PAD_LEFT),
                'name' => 'Gradebook: ' . $course->title,
                'status' => 'active',
            ]);
            
            // Add 5 grade items per gradebook
            for ($j = 1; $j <= 5; $j++) {
                GradeItem::create([
                    'tenant_id' => $this->tenant->id,
                    'gradebook_id' => $gradebook->id,
                    'item_type' => ['quiz', 'assignment', 'participation', 'final_exam', 'project'][$j - 1],
                    'title' => ['Quiz', 'Assignment', 'Participation', 'Final Exam', 'Project'][$j - 1],
                    'max_score' => 100,
                    'weight' => 20,
                    'sequence' => $j,
                ]);
            }
        }
        
        echo "  ✓ Created 20 gradebooks\n";
    }

    protected function seedSurveys(): void
    {
        echo "📍 Creating 50 Surveys...\n";
        
        for ($i = 1; $i <= 50; $i++) {
            $survey = Survey::create([
                'tenant_id' => $this->tenant->id,
                'code' => 'SV' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'title' => 'Khảo sát ' . $i,
                'description' => $this->faker->paragraph(),
                'survey_type' => rand(1, 2) === 1 ? 'satisfaction' : 'feedback',
                'status' => 'active',
                'published_at' => now()->subMonths(rand(0, 6)),
            ]);
            
            // Add 10 questions per survey
            for ($j = 1; $j <= 10; $j++) {
                SurveyQuestion::create([
                    'survey_id' => $survey->id,
                    'question_text' => 'Câu hỏi ' . $j . '?',
                    'question_type' => ['likert', 'yes_no', 'short_text'][$j % 3],
                    'sequence' => $j,
                ]);
            }
        }
        
        echo "  ✓ Created 50 surveys\n";
    }

    protected function seedForumDiscussions(): void
    {
        echo "📍 Creating Forum Discussions...\n";
        
        $topicCount = 0;
        $postCount = 0;
        
        // 100 topics across courses
        for ($i = 1; $i <= 100; $i++) {
            $course = $this->courses[$i % count($this->courses)];
            $author = $this->teachers[$i % count($this->teachers)];
            
            $thread = DiscussionThread::create([
                'tenant_id' => $this->tenant->id,
                'course_id' => $course->id,
                'author_id' => $author->id,
                'title' => 'Chủ đề thảo luận ' . $i,
                'content' => $this->faker->paragraph(),
                'status' => 'published',
                'created_at' => now()->subDays(rand(1, 90)),
            ]);
            
            $topicCount++;
            
            // 10 posts per topic
            for ($j = 1; $j <= 10; $j++) {
                $poster = rand(1, 2) === 1 
                    ? $this->students[$j % count($this->students)]
                    : $this->teachers[$j % count($this->teachers)];
                
                DiscussionPost::create([
                    'tenant_id' => $this->tenant->id,
                    'thread_id' => $thread->id,
                    'author_id' => $poster->id,
                    'content' => $this->faker->sentence(),
                    'created_at' => now()->subDays(rand(1, 30)),
                ]);
                
                $postCount++;
            }
        }
        
        echo "  ✓ Created $topicCount forum topics with $postCount posts\n";
    }

    protected function seedLearningProgress(): void
    {
        echo "📍 Creating 10000 Learning Progress Records...\n";
        
        $progressCount = 0;
        
        // For each enrollment, create 10 progress records
        $enrollments = \App\Models\Enrollment::inRandomOrder()->limit(1000)->get();
        
        foreach ($enrollments as $enrollment) {
            for ($i = 1; $i <= 10; $i++) {
                UserCourseProgress::create([
                    'tenant_id' => $this->tenant->id,
                    'user_id' => $enrollment->user_id,
                    'course_id' => $enrollment->course_id,
                    'progress_percent' => rand(0, 100),
                    'last_accessed_at' => now()->subDays(rand(0, 30)),
                    'completed_components_count' => rand(0, 20),
                    'risk_level' => ['low', 'medium', 'high'][rand(0, 2)],
                ]);
                
                $progressCount++;
                if ($progressCount % 2500 === 0) {
                    echo "  ✓ Created $progressCount progress records\n";
                }
            }
        }
    }

    /**
     * Disable foreign key checks for faster bulk inserts
     * Handles both MySQL and SQLite
     */
    protected function disableForeignKeyChecks(): void
    {
        if ($this->isUsingSqlite()) {
            \DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }
    }

    /**
     * Re-enable foreign key checks
     * Handles both MySQL and SQLite
     */
    protected function enableForeignKeyChecks(): void
    {
        if ($this->isUsingSqlite()) {
            \DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    /**
     * Check if using SQLite database
     */
    protected function isUsingSqlite(): bool
    {
        return \DB::connection()->getDriverName() === 'sqlite';
    }
}
