<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CoreSeeder::class,
            CourseStudioSeeder::class,
        ]);

        if (filter_var(env('ERALMS_SEED_REALISTIC_LEARNING_CONTENT', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(RealisticLearningContentSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_MEANINGFUL_COURSE_LESSONS', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(MeaningfulCourseLessonSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_COURSE_QUIZ_ENROLLMENTS', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(CourseQuizEnrollmentSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_SHARED_REPOSITORY', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(SharedLearningRepositorySeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_LEARNING_PATH', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(LearningPathSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_VIDEO_PLATFORM', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(VideoPlatformSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_QUESTION_BANK', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(QuestionBankSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_REALISTIC_QUESTION_BANK', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(RealisticQuestionBankSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_EXAM', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(ExamSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_ASSIGNMENT', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(AssignmentSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_GRADEBOOK', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(GradebookSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_ATTENDANCE', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(AttendanceSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_INTEGRATION', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(IntegrationSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_API_OPERATIONS', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(ApiOperationsSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_LEARNING_STANDARDS', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(LearningStandardsSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_ENROLLMENT', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(EnrollmentSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_CAREER_PORTFOLIO', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(CareerPortfolioSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_DIGITAL_CREDENTIAL', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(DigitalCredentialSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_OBE', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(OBEAccreditationSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_LEARNING_COMMUNITY', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(LearningCommunitySeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_SURVEY', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(SurveySeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_AI_LEARNING', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(AiLearningSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_LEARNING_ANALYTICS', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(LearningAnalyticsSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_DASHBOARD_ANALYTICS', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(DashboardAnalyticsSeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_ACTION_REGISTRY', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(LmsActionRegistrySeeder::class);
        }

        if (filter_var(env('ERALMS_SEED_PERFORMANCE_LOAD', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(PerformanceLoadSeeder::class);
        }

        $this->call([
            LearnerDemoSeeder::class,
            DemoAccountsSeeder::class,
            DemoLearningContentSeeder::class,
        ]);

        if (filter_var(env('ERALMS_SEED_EXAM_DATA_REPAIR', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(ExamDataRepairSeeder::class);
        }
    }
}
