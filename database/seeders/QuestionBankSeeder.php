<?php

namespace Database\Seeders;

use App\Models\ExamBlueprint;
use App\Models\LearningOutcome;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionCategory;
use App\Models\QuestionTag;
use App\Models\Tenant;
use App\Services\QuestionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuestionBankSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $teacherId = DB::table('lms_users')->where('tenant_id', $tenant->id)->where('user_type', 'teacher')->value('id') ?: 1;
        $banks = [
            ['QB-MC', 'Bộ đề nền tảng chung theo chuẩn quốc tế'],
            ['QB-CNTT', 'Bộ đề Công nghệ thông tin theo chuẩn ACM/IEEE'],
            ['QB-DL', 'Bộ đề Du lịch theo chuẩn nghề ASEAN'],
            ['QB-NN', 'Bộ đề Ngoại ngữ theo Khung năng lực 6 bậc'],
            ['QB-VH9', 'Bộ đề Văn hóa 9+ theo chuẩn chương trình mới'],
        ];

        foreach ($banks as [$code, $name]) {
            $bank = QuestionBank::query()->updateOrCreate(['tenant_id' => $tenant->id, 'code' => $code], ['name' => $name, 'description' => "Ngân hàng câu hỏi {$name}, phân loại theo Bloom, CLO/PLO, độ khó và phạm vi sử dụng.", 'visibility' => 'tenant', 'status' => 'published', 'owner_id' => $teacherId, 'settings' => ['seed' => true, 'standard_profile' => 'international']]);
            foreach (['C1' => 'Chương 1', 'C2' => 'Chương 2', 'C3' => 'Chương 3'] as $catCode => $catName) {
                QuestionCategory::query()->updateOrCreate(['question_bank_id' => $bank->id, 'code' => $catCode], ['tenant_id' => $tenant->id, 'name' => $catName, 'sort_order' => (int) substr($catCode, 1), 'metadata' => ['seed' => true]]);
            }
        }

        foreach (['CLO1' => 'Nhận biết kiến thức nền tảng', 'CLO2' => 'Vận dụng quy trình', 'CLO3' => 'Phân tích tình huống', 'PLO1' => 'Năng lực chuyên môn', 'PLO2' => 'Năng lực số'] as $code => $name) {
            LearningOutcome::query()->updateOrCreate(['tenant_id' => $tenant->id, 'code' => $code], ['name' => $name, 'type' => str_starts_with($code, 'PLO') ? 'PLO' : 'CLO', 'description' => $name, 'status' => 'active']);
        }

        foreach ([['Nhận biết', '#2563eb'], ['Vận dụng', '#059669'], ['Tình huống', '#d97706'], ['Thi cuối kỳ', '#7c3aed']] as [$name, $color]) {
            QuestionTag::query()->updateOrCreate(['tenant_id' => $tenant->id, 'name' => $name], ['color' => $color]);
        }

        $service = app(QuestionService::class);
        $types = ['single_choice', 'multiple_choice', 'true_false', 'essay', 'fill_blank', 'matching', 'ordering', 'audio', 'image', 'video'];
        $difficulties = ['easy', 'medium', 'hard', 'expert'];
        $blooms = ['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'];
        $allBanks = QuestionBank::query()->where('tenant_id', $tenant->id)->get();
        $outcomes = LearningOutcome::query()->where('tenant_id', $tenant->id)->pluck('id')->all();

        for ($i = 1; $i <= 500; $i++) {
            $bank = $allBanks[($i - 1) % $allBanks->count()];
            $categoryId = QuestionCategory::query()->where('question_bank_id', $bank->id)->inRandomOrder()->value('id');
            $type = $types[($i - 1) % count($types)];
            $payload = $this->payloadForType($type, $tenant->id, $bank->id, $categoryId, $teacherId, $i, $difficulties, $blooms);
            $question = Question::query()->where('tenant_id', $tenant->id)->where('code', $payload['code'])->first();
            if (! $question) {
                $question = $service->createQuestion($payload);
            }
            $question->forceFill(['status' => $i % 5 === 0 ? 'published' : 'approved', 'approved_by' => $teacherId, 'approved_at' => now()])->save();
            DB::table('question_outcome_map')->updateOrInsert(['question_id' => $question->id, 'outcome_id' => $outcomes[$i % count($outcomes)]], ['tenant_id' => $tenant->id, 'weight' => 1]);
        }

        foreach ($allBanks->take(5) as $index => $bank) {
            ExamBlueprint::query()->updateOrCreate(['tenant_id' => $tenant->id, 'code' => 'BP-'.($index + 1)], [
                'question_bank_id' => $bank->id, 'name' => 'Ma trận đề '.$bank->name, 'description' => 'Ma trận mẫu để sinh đề ngẫu nhiên theo chuẩn đánh giá quốc tế', 'total_questions' => 30, 'total_score' => 20, 'duration_minutes' => 45, 'status' => 'active', 'created_by' => $teacherId,
                'config' => ['sections' => [
                    ['name' => 'Nhận biết', 'question_count' => 20, 'difficulty' => ['easy'], 'bloom_level' => ['remember', 'understand'], 'outcomes' => ['CLO1'], 'score_each' => 0.5],
                    ['name' => 'Vận dụng', 'question_count' => 10, 'difficulty' => ['medium', 'hard'], 'bloom_level' => ['apply', 'analyze'], 'outcomes' => ['CLO2', 'CLO3'], 'score_each' => 1],
                ], 'randomize_questions' => true, 'randomize_options' => true],
            ]);
        }
    }

    private function payloadForType(string $type, int $tenantId, int $bankId, ?int $categoryId, int $ownerId, int $i, array $difficulties, array $blooms): array
    {
        $base = ['tenant_id' => $tenantId, 'question_bank_id' => $bankId, 'category_id' => $categoryId, 'code' => 'Q'.str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'question_type' => $type, 'title' => "Câu hỏi mẫu {$i}", 'stem' => "Nội dung câu hỏi mẫu {$i} thuộc loại {$type}.", 'difficulty' => $difficulties[$i % count($difficulties)], 'bloom_level' => $blooms[$i % count($blooms)], 'default_score' => 1, 'owner_id' => $ownerId, 'metadata' => ['seed' => true]];
        return match ($type) {
            'single_choice' => $base + ['options' => [['content' => 'Đáp án A', 'is_correct' => true], ['content' => 'Đáp án B', 'is_correct' => false], ['content' => 'Đáp án C', 'is_correct' => false], ['content' => 'Đáp án D', 'is_correct' => false]]],
            'multiple_choice' => $base + ['options' => [['content' => 'Ý đúng 1', 'is_correct' => true], ['content' => 'Ý đúng 2', 'is_correct' => true], ['content' => 'Ý sai', 'is_correct' => false]]],
            'true_false' => $base + ['options' => [['content' => 'Đúng', 'is_correct' => true], ['content' => 'Sai', 'is_correct' => false]]],
            'fill_blank' => array_replace($base, ['stem' => 'Điền thuật ngữ phù hợp vào {{blank_1}}.']) + ['fill_blank_answers' => [['blank_key' => 'blank_1', 'accepted_answer' => 'EraLMS', 'score_weight' => 1]]],
            'matching' => $base + ['matching_pairs' => [['left_content' => 'CLO', 'right_content' => 'Chuẩn đầu ra học phần'], ['left_content' => 'PLO', 'right_content' => 'Chuẩn đầu ra chương trình']]],
            'ordering' => $base + ['options' => [['content' => 'Bước 1'], ['content' => 'Bước 2'], ['content' => 'Bước 3']]],
            'audio', 'image', 'video' => array_replace($base, ['metadata' => ['media_url' => 'https://example.edu/media/question-'.$i, 'seed' => true]]),
            default => $base,
        };
    }
}
