<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamBlueprint;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\LearningOutcome;
use App\Models\LmsUser;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RealisticQuestionBankSeeder extends Seeder
{
    private const TOTAL_QUESTIONS = 10000;

    public function run(): void
    {
        DB::disableQueryLog();

        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $ownerId = LmsUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_type', 'teacher')
            ->value('id') ?: 1;

        DB::transaction(function () use ($tenant, $ownerId) {
            $questionIds = Question::query()
                ->where('tenant_id', $tenant->id)
                ->where('code', 'like', 'RQ-%')
                ->pluck('id');

            foreach ($questionIds->chunk(1000) as $chunk) {
                DB::table('question_options')->whereIn('question_id', $chunk)->delete();
                DB::table('question_outcome_map')->whereIn('question_id', $chunk)->delete();
            }

            $banks = $this->bankSpecs();
            $bankModels = [];
            $categoryModels = [];
            $outcomeModels = [];

            foreach ($banks as $spec) {
                $bank = QuestionBank::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'code' => $spec['code']],
                    [
                        'name' => $spec['name'],
                        'description' => 'Ngân hàng câu hỏi thực tế cho '.$spec['name'].', phân tầng Easy/Medium/Hard và mapping CLO - Bloom - Difficulty.',
                        'visibility' => 'tenant',
                        'status' => 'published',
                        'owner_id' => $ownerId,
                        'settings' => [
                            'realistic_question_bank' => true,
                            'group' => $spec['group'],
                            'target_count' => $spec['count'],
                            'difficulty_distribution' => ['easy' => 0.4, 'medium' => 0.4, 'hard' => 0.2],
                        ],
                    ]
                );
                $bankModels[$spec['code']] = $bank;

                foreach ($spec['topics'] as $index => $topic) {
                    $categoryModels[$spec['code']][$index] = QuestionCategory::query()->updateOrCreate(
                        ['question_bank_id' => $bank->id, 'code' => 'RQ-T'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)],
                        [
                            'tenant_id' => $tenant->id,
                            'name' => $topic,
                            'description' => 'Chủ đề '.$topic.' trong '.$spec['name'],
                            'sort_order' => $index + 1,
                            'metadata' => ['realistic_question_bank' => true],
                        ]
                    );
                }

                foreach (range(1, 3) as $index) {
                    $code = $spec['code'].'-CLO'.$index;
                    $outcomeModels[$spec['code']][$index - 1] = LearningOutcome::query()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'code' => $code],
                        [
                            'name' => 'CLO'.$index.' - '.$this->cloName($spec, $index),
                            'type' => 'CLO',
                            'description' => $this->cloDescription($spec, $index),
                            'status' => 'active',
                        ]
                    );
                }
            }

            $this->seedQuestions($tenant->id, $ownerId, $banks, $bankModels, $categoryModels, $outcomeModels);
            $this->seedBlueprintsAndMockExams($tenant->id, $ownerId, $banks, $bankModels);
        });
    }

    private function seedQuestions(int $tenantId, int $ownerId, array $banks, array $bankModels, array $categoryModels, array $outcomeModels): void
    {
        $now = now();
        $questionRows = [];
        $questionMeta = [];

        foreach ($banks as $spec) {
            $bank = $bankModels[$spec['code']];
            $topics = $spec['topics'];
            $categories = $categoryModels[$spec['code']];
            $outcomes = $outcomeModels[$spec['code']];

            foreach (range(1, $spec['count']) as $index) {
                $difficulty = $this->difficultyFor($index, $spec['count']);
                $bloom = $this->bloomFor($difficulty, $index);
                $topicIndex = ($index - 1) % count($topics);
                $type = $this->questionTypeFor($spec, $index);
                $code = $spec['code'].'-Q'.str_pad((string) $index, 5, '0', STR_PAD_LEFT);
                $stem = $this->stemFor($spec, $topics[$topicIndex], $difficulty, $bloom, $index, $type);

                $questionRows[] = [
                    'tenant_id' => $tenantId,
                    'question_bank_id' => $bank->id,
                    'category_id' => $categories[$topicIndex]->id,
                    'code' => $code,
                    'question_type' => $type,
                    'title' => $spec['short'].' - '.$topics[$topicIndex].' - câu '.$index,
                    'stem' => $stem,
                    'explanation' => $this->explanationFor($spec, $topics[$topicIndex], $difficulty, $bloom),
                    'difficulty' => $difficulty,
                    'bloom_level' => $bloom,
                    'default_score' => $type === 'essay' ? 2 : 1,
                    'penalty_score' => 0,
                    'time_limit_seconds' => $type === 'essay' ? 600 : ($difficulty === 'hard' ? 150 : 90),
                    'status' => 'approved',
                    'owner_id' => $ownerId,
                    'approved_by' => $ownerId,
                    'approved_at' => $now,
                    'metadata' => json_encode([
                        'realistic_question_bank' => true,
                        'group' => $spec['group'],
                        'subject' => $spec['subject'],
                        'skill' => $spec['skill'] ?? null,
                        'topic' => $topics[$topicIndex],
                        'clo_code' => $outcomes[($index - 1) % 3]->code,
                        'bloom' => $bloom,
                        'difficulty' => $difficulty,
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $questionMeta[$code] = [
                    'type' => $type,
                    'spec' => $spec,
                    'topic' => $topics[$topicIndex],
                    'difficulty' => $difficulty,
                    'outcome_id' => $outcomes[($index - 1) % 3]->id,
                ];

                if (count($questionRows) >= 1000) {
                    Question::query()->upsert($questionRows, ['tenant_id', 'code'], [
                        'question_bank_id', 'category_id', 'question_type', 'title', 'stem', 'explanation',
                        'difficulty', 'bloom_level', 'default_score', 'penalty_score', 'time_limit_seconds',
                        'status', 'owner_id', 'approved_by', 'approved_at', 'metadata', 'updated_at',
                    ]);
                    $questionRows = [];
                }
            }
        }

        if ($questionRows !== []) {
            Question::query()->upsert($questionRows, ['tenant_id', 'code'], [
                'question_bank_id', 'category_id', 'question_type', 'title', 'stem', 'explanation',
                'difficulty', 'bloom_level', 'default_score', 'penalty_score', 'time_limit_seconds',
                'status', 'owner_id', 'approved_by', 'approved_at', 'metadata', 'updated_at',
            ]);
        }

        Question::query()
            ->where('tenant_id', $tenantId)
            ->where('code', 'like', 'RQ-%')
            ->select(['id', 'code'])
            ->orderBy('id')
            ->chunkById(1000, function ($questions) use ($tenantId, $questionMeta, $now) {
                $optionRows = [];
                $mapRows = [];

                foreach ($questions as $question) {
                    $meta = $questionMeta[$question->code] ?? null;
                    if (! $meta) {
                        continue;
                    }

                    $mapRows[] = [
                        'tenant_id' => $tenantId,
                        'question_id' => $question->id,
                        'outcome_id' => $meta['outcome_id'],
                        'weight' => 1,
                    ];

                    foreach ($this->optionsFor($meta['spec'], $meta['topic'], $meta['type'], $meta['difficulty']) as $sort => [$key, $content, $correct]) {
                        $optionRows[] = [
                            'tenant_id' => $tenantId,
                            'question_id' => $question->id,
                            'option_key' => $key,
                            'content' => $content,
                            'is_correct' => $correct,
                            'score_weight' => $correct ? 1 : 0,
                            'feedback' => $correct ? 'Đúng: phương án này phù hợp với chuẩn đầu ra và dữ kiện câu hỏi.' : 'Chưa đúng: cần đối chiếu lại khái niệm, dữ kiện hoặc quy trình trong học liệu.',
                            'sort_order' => $sort + 1,
                            'media_url' => null,
                            'metadata' => json_encode(['realistic_question_bank' => true], JSON_UNESCAPED_UNICODE),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                foreach (array_chunk($optionRows, 1000) as $chunk) {
                    DB::table('question_options')->insert($chunk);
                }
                foreach (array_chunk($mapRows, 1000) as $chunk) {
                    DB::table('question_outcome_map')->insertOrIgnore($chunk);
                }
            });
    }

    private function seedBlueprintsAndMockExams(int $tenantId, int $ownerId, array $banks, array $bankModels): void
    {
        foreach ($banks as $index => $spec) {
            $bank = $bankModels[$spec['code']];
            $blueprint = ExamBlueprint::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => 'RQ-BP-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'question_bank_id' => $bank->id,
                    'name' => 'Blueprint - '.$spec['name'],
                    'description' => 'Ma trận đề theo Easy/Medium/Hard, CLO, Bloom và chủ đề cho '.$spec['name'].'.',
                    'total_questions' => 40,
                    'total_score' => 40,
                    'duration_minutes' => $this->durationFor($spec),
                    'config' => [
                        'realistic_question_bank' => true,
                        'sections' => [
                            ['name' => 'Easy - nền tảng', 'question_count' => 16, 'difficulty' => ['easy'], 'bloom_level' => ['remember', 'understand'], 'score_each' => 1],
                            ['name' => 'Medium - vận dụng', 'question_count' => 16, 'difficulty' => ['medium'], 'bloom_level' => ['apply', 'analyze'], 'score_each' => 1],
                            ['name' => 'Hard - phân tích nâng cao', 'question_count' => 8, 'difficulty' => ['hard'], 'bloom_level' => ['analyze', 'evaluate', 'create'], 'score_each' => 1],
                        ],
                        'clo_balance' => ['CLO1' => 0.35, 'CLO2' => 0.4, 'CLO3' => 0.25],
                        'randomize_questions' => true,
                        'randomize_options' => true,
                    ],
                    'status' => 'active',
                    'created_by' => $ownerId,
                ]
            );

            $exam = Exam::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => 'RQ-MOCK-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'question_bank_id' => $bank->id,
                    'blueprint_id' => $blueprint->id,
                    'title' => 'Mock Exam - '.$spec['name'],
                    'description' => 'Đề thi thử sinh từ ngân hàng '.$spec['name'].' theo blueprint Easy/Medium/Hard.',
                    'exam_type' => $this->mockTypeFor($spec),
                    'delivery_mode' => in_array($spec['group'], ['TOEIC', 'IELTS', 'TOEFL', 'HSK', 'TOPIK'], true) ? 'remote_proctored' : 'self_paced',
                    'status' => 'published',
                    'total_score' => 40,
                    'pass_score' => $spec['group'] === 'THPT' ? 20 : 24,
                    'duration_minutes' => $this->durationFor($spec),
                    'max_attempts' => 2,
                    'shuffle_questions' => true,
                    'shuffle_options' => true,
                    'show_result_mode' => 'after_submit',
                    'show_correct_answers' => false,
                    'open_at' => now()->subDays(7),
                    'close_at' => now()->addDays(90),
                    'settings' => [
                        'realistic_question_bank' => true,
                        'source_blueprint' => $blueprint->code,
                        'mapping' => ['CLO', 'Bloom', 'Difficulty'],
                    ],
                    'created_by' => $ownerId,
                    'approved_by' => $ownerId,
                    'approved_at' => now()->subDays(6),
                ]
            );

            $section = ExamSection::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'exam_id' => $exam->id, 'sort_order' => 1],
                [
                    'title' => 'Đề thi thử '.$spec['short'],
                    'description' => '40 câu đại diện cho ngân hàng '.$spec['name'].'.',
                    'question_count' => 40,
                    'score' => 40,
                    'config' => ['realistic_question_bank' => true],
                ]
            );

            ExamQuestion::query()->where('tenant_id', $tenantId)->where('exam_id', $exam->id)->delete();
            $questionIds = Question::query()
                ->where('tenant_id', $tenantId)
                ->where('question_bank_id', $bank->id)
                ->whereIn('difficulty', ['easy', 'medium', 'hard'])
                ->orderByRaw("CASE difficulty WHEN 'easy' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->orderBy('id')
                ->limit(40)
                ->pluck('id');

            $rows = [];
            foreach ($questionIds as $sort => $questionId) {
                $rows[] = [
                    'tenant_id' => $tenantId,
                    'exam_id' => $exam->id,
                    'section_id' => $section->id,
                    'question_id' => $questionId,
                    'score' => 1,
                    'sort_order' => $sort + 1,
                    'required' => true,
                    'metadata' => json_encode(['realistic_question_bank' => true], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('exam_questions')->insert($rows);
        }
    }

    private function bankSpecs(): array
    {
        $banks = [];
        foreach ([
            ['Toán', 500, ['Hàm số', 'Mũ logarit', 'Nguyên hàm', 'Tích phân', 'Hình học Oxyz', 'Số phức']],
            ['Văn', 300, ['Đọc hiểu', 'Nghị luận xã hội', 'Nghị luận văn học', 'Thơ hiện đại', 'Truyện ngắn']],
            ['Anh', 500, ['Grammar', 'Reading', 'Listening', 'Vocabulary', 'Sentence transformation']],
            ['Lý', 300, ['Dao động', 'Sóng', 'Điện xoay chiều', 'Quang học', 'Hạt nhân']],
            ['Hóa', 300, ['Este', 'Kim loại', 'Điện phân', 'Amin amino axit', 'Hóa vô cơ']],
            ['Sinh', 300, ['Di truyền', 'Tiến hóa', 'Sinh thái', 'Phả hệ', 'Quần thể']],
            ['Sử', 300, ['Việt Nam 1919-1945', 'Kháng chiến chống Pháp', 'Kháng chiến chống Mỹ', 'Chiến tranh lạnh', 'ASEAN']],
            ['Địa', 300, ['Atlat', 'Dân cư', 'Nông nghiệp', 'Công nghiệp', 'Vùng kinh tế']],
        ] as [$subject, $count, $topics]) {
            $banks[] = $this->bank('THPT', $subject, null, 'RQ-THPT-'.Str::upper(Str::ascii($subject)), 'THPT '.$subject, $count, $topics);
        }

        foreach ([
            ['4 môn', 'Toán'], ['4 môn', 'Văn'], ['4 môn', 'Anh'], ['4 môn', 'Sử Địa'],
            ['7 môn', 'Toán'], ['7 môn', 'Văn'], ['7 môn', 'Anh'], ['7 môn', 'Lý'], ['7 môn', 'Hóa'], ['7 môn', 'Sinh'], ['7 môn', 'Sử'],
        ] as [$track, $subject]) {
            $banks[] = $this->bank('Văn hóa 9+', $subject, $track, 'RQ-9PLUS-'.Str::upper(Str::slug($track.'-'.$subject, '-')), 'Văn hóa 9+ '.$track.' - '.$subject, 150, ['Kiến thức nền', 'Bài học trọng tâm', 'Luyện tập', 'Ứng dụng thực tế', 'Đánh giá học kỳ']);
        }

        foreach ([
            ['Tin học cơ sở', 350, ['Hệ điều hành', 'Internet', 'Bảng tính', 'Soạn thảo', 'An toàn dữ liệu']],
            ['Mạng', 350, ['Mô hình OSI', 'TCP/IP', 'Subnet', 'Switching', 'Wireless']],
            ['Web', 350, ['HTML CSS', 'JavaScript', 'HTTP', 'Form validation', 'Triển khai website']],
            ['Database', 350, ['ERD', 'SQL SELECT', 'JOIN', 'Transaction', 'Backup restore']],
        ] as [$subject, $count, $topics]) {
            $banks[] = $this->bank('CNTT', $subject, null, 'RQ-CNTT-'.Str::upper(Str::slug($subject, '-')), 'CNTT - '.$subject, $count, $topics);
        }

        foreach ([
            ['Lễ tân', ['Quy trình check-in', 'Xử lý phàn nàn', 'Đặt phòng', 'Giao tiếp khách', 'Báo cáo ca']],
            ['Nhà hàng', ['Setup bàn', 'Phục vụ món', 'An toàn thực phẩm', 'Upsell', 'Thanh toán']],
            ['Khách sạn', ['Buồng phòng', 'Giặt là', 'Kiểm tra phòng', 'An ninh khách sạn', 'Dịch vụ bổ sung']],
        ] as [$subject, $topics]) {
            $banks[] = $this->bank('Du lịch', $subject, null, 'RQ-DULICH-'.Str::upper(Str::slug($subject, '-')), 'Du lịch - '.$subject, 300, $topics);
        }

        foreach ([
            ['TOEIC', 'Listening', 350, ['Photographs', 'Question response', 'Conversations', 'Talks', 'Workplace context']],
            ['TOEIC', 'Reading', 350, ['Incomplete sentences', 'Text completion', 'Single passage', 'Double passage', 'Vocabulary in context']],
            ['IELTS', 'Listening', 250, ['Section 1', 'Section 2', 'Section 3', 'Section 4', 'Map labeling']],
            ['IELTS', 'Reading', 250, ['Skimming', 'Matching headings', 'True False Not Given', 'Summary completion', 'Inference']],
            ['IELTS', 'Writing', 250, ['Task 1 chart', 'Task 1 process', 'Task 2 opinion', 'Task 2 discussion', 'Coherence cohesion']],
            ['IELTS', 'Speaking', 250, ['Part 1 familiar topics', 'Part 2 cue card', 'Part 3 abstract discussion', 'Fluency', 'Pronunciation']],
            ['TOEFL', null, 400, ['Reading academic', 'Listening lecture', 'Speaking integrated', 'Writing integrated', 'Writing academic discussion']],
        ] as [$test, $skill, $count, $topics]) {
            $code = 'RQ-'.$test.($skill ? '-'.Str::upper($skill) : '');
            $banks[] = $this->bank($test, $test, $skill, $code, $test.($skill ? ' - '.$skill : ''), $count, $topics);
        }

        foreach ([
            ['HSK1', 200], ['HSK2', 200], ['HSK3', 200], ['HSK4', 200],
        ] as [$level, $count]) {
            $banks[] = $this->bank('HSK', $level, null, 'RQ-HSK-'.$level, 'Tiếng Hoa - '.$level, $count, ['Từ vựng', 'Ngữ pháp', 'Nghe hiểu', 'Đọc hiểu', 'Giao tiếp tình huống']);
        }

        foreach ([['TOPIK I', 175], ['TOPIK II', 175]] as [$level, $count]) {
            $banks[] = $this->bank('TOPIK', $level, null, 'RQ-TOPIK-'.Str::upper(Str::slug($level, '-')), 'Tiếng Hàn - '.$level, $count, ['Từ vựng', 'Ngữ pháp', 'Nghe hiểu', 'Đọc hiểu', 'Viết câu']);
        }

        return $banks;
    }

    private function bank(string $group, string $subject, ?string $skill, string $code, string $name, int $count, array $topics): array
    {
        return [
            'group' => $group,
            'subject' => $subject,
            'skill' => $skill,
            'code' => $code,
            'name' => $name,
            'short' => $name,
            'count' => $count,
            'topics' => $topics,
        ];
    }

    private function difficultyFor(int $index, int $total): string
    {
        $ratio = $index / max(1, $total);

        return match (true) {
            $ratio <= 0.4 => 'easy',
            $ratio <= 0.8 => 'medium',
            default => 'hard',
        };
    }

    private function bloomFor(string $difficulty, int $index): string
    {
        return match ($difficulty) {
            'easy' => ['remember', 'understand'][$index % 2],
            'medium' => ['apply', 'analyze'][$index % 2],
            default => ['analyze', 'evaluate', 'create'][$index % 3],
        };
    }

    private function questionTypeFor(array $spec, int $index): string
    {
        if (($spec['skill'] ?? null) === 'Writing' || ($spec['skill'] ?? null) === 'Speaking') {
            return $index % 3 === 0 ? 'essay' : 'single_choice';
        }
        if ($spec['group'] === 'Du lịch' && $index % 5 === 0) {
            return 'essay';
        }

        return match ($index % 10) {
            0 => 'essay',
            1, 2 => 'multiple_choice',
            3 => 'true_false',
            default => 'single_choice',
        };
    }

    private function stemFor(array $spec, string $topic, string $difficulty, string $bloom, int $index, string $type): string
    {
        if ($type === 'essay') {
            return match ($spec['group']) {
                'IELTS' => 'Viết câu trả lời cho chủ đề '.$topic.', bảo đảm lập luận rõ, ví dụ phù hợp và kiểm soát lỗi ngôn ngữ.',
                'TOEFL' => 'Trình bày phản hồi học thuật cho nhiệm vụ '.$topic.', nêu quan điểm và dùng bằng chứng từ bài đọc hoặc bài nghe.',
                'Du lịch' => 'Phân tích tình huống '.$topic.' khi khách hàng không hài lòng và đề xuất quy trình xử lý trong ca làm việc.',
                default => 'Trình bày cách giải quyết nhiệm vụ '.$topic.' ở mức '.$difficulty.', nêu rõ dữ kiện, lập luận và kết luận.',
            };
        }

        return match ($spec['group']) {
            'THPT' => 'Trong chủ đề '.$topic.' của môn '.$spec['subject'].', lựa chọn phương án phù hợp nhất với yêu cầu nhận thức '.$bloom.'.',
            'Văn hóa 9+' => 'Với bài học '.$topic.' thuộc '.$spec['name'].', học viên cần chọn cách xử lý đúng để hoàn thành hoạt động học tập.',
            'CNTT' => 'Trong tình huống kỹ thuật về '.$topic.', phương án nào là đúng theo quy trình triển khai và kiểm tra hệ thống?',
            'Du lịch' => 'Khi phục vụ khách ở tình huống '.$topic.', nhân viên nên chọn phương án nào để bảo đảm tiêu chuẩn dịch vụ?',
            'TOEIC' => 'Trong ngữ cảnh TOEIC '.$topic.', lựa chọn đáp án phù hợp nhất với thông tin hoặc cấu trúc được hỏi.',
            'IELTS' => 'Trong nhiệm vụ IELTS '.$topic.', lựa chọn chiến lược hoặc câu trả lời phù hợp nhất.',
            'TOEFL' => 'Trong bài TOEFL về '.$topic.', lựa chọn phương án thể hiện đúng ý chính, chi tiết hoặc suy luận.',
            'HSK' => 'Trong ngữ cảnh '.$spec['subject'].' về '.$topic.', chọn cách dùng từ hoặc cấu trúc tiếng Hoa phù hợp nhất.',
            'TOPIK' => 'Trong ngữ cảnh '.$spec['subject'].' về '.$topic.', chọn cách dùng từ hoặc cấu trúc tiếng Hàn phù hợp nhất.',
            default => 'Chọn đáp án phù hợp nhất cho chủ đề '.$topic.'.',
        };
    }

    private function optionsFor(array $spec, string $topic, string $type, string $difficulty): array
    {
        if ($type === 'essay') {
            return [];
        }

        if ($type === 'true_false') {
            return [
                ['A', 'Đúng, vì nhận định bám sát dữ kiện và chuẩn đầu ra của chủ đề '.$topic.'.', true],
                ['B', 'Sai, vì nhận định bỏ qua điều kiện quan trọng của chủ đề '.$topic.'.', false],
            ];
        }

        if ($type === 'multiple_choice') {
            return [
                ['A', 'Xác định đúng yêu cầu, dữ kiện và tiêu chí đánh giá của chủ đề '.$topic.'.', true],
                ['B', 'Đối chiếu kết quả với CLO và mức độ '.$difficulty.' trước khi kết luận.', true],
                ['C', 'Chọn đáp án theo cảm tính mà không kiểm tra dữ kiện.', false],
                ['D', 'Bỏ qua phần phản hồi vì không ảnh hưởng đến kết quả học tập.', false],
            ];
        }

        return [
            ['A', $this->correctOptionFor($spec, $topic), true],
            ['B', 'Chỉ ghi nhớ đáp án cuối mà không xem xét quy trình hoặc ngữ cảnh.', false],
            ['C', 'Áp dụng một mẹo làm bài chung cho mọi trường hợp, kể cả khi dữ kiện thay đổi.', false],
            ['D', 'Bỏ qua tiêu chí đánh giá CLO, Bloom và độ khó của câu hỏi.', false],
        ];
    }

    private function correctOptionFor(array $spec, string $topic): string
    {
        return match ($spec['group']) {
            'THPT' => 'Phân tích đúng dữ kiện của '.$topic.' rồi chọn phương pháp giải phù hợp.',
            'CNTT' => 'Kiểm tra yêu cầu, cấu hình và kết quả đầu ra theo từng bước của '.$topic.'.',
            'Du lịch' => 'Lắng nghe khách, xác nhận nhu cầu và xử lý theo chuẩn dịch vụ của '.$topic.'.',
            'TOEIC', 'IELTS', 'TOEFL' => 'Dựa vào tín hiệu ngôn ngữ, ngữ cảnh và mục đích giao tiếp trong '.$topic.'.',
            'HSK' => 'Chọn cấu trúc và từ vựng phù hợp với ngữ cảnh giao tiếp '.$topic.'.',
            'TOPIK' => 'Chọn biểu hiện tiếng Hàn phù hợp với ngữ cảnh và quan hệ giao tiếp '.$topic.'.',
            default => 'Xác định yêu cầu chính của '.$topic.' rồi đối chiếu với kiến thức đã học.',
        };
    }

    private function explanationFor(array $spec, string $topic, string $difficulty, string $bloom): string
    {
        return 'Câu hỏi kiểm tra '.$topic.' ở mức '.$difficulty.', gắn với Bloom '.$bloom.' và yêu cầu người học đối chiếu dữ kiện với chuẩn đầu ra.';
    }

    private function cloName(array $spec, int $index): string
    {
        return match ($index) {
            1 => 'Nhận biết và giải thích kiến thức nền của '.$spec['name'],
            2 => 'Vận dụng kiến thức '.$spec['name'].' vào bài tập hoặc tình huống',
            default => 'Phân tích, đánh giá và cải thiện kết quả học tập trong '.$spec['name'],
        };
    }

    private function cloDescription(array $spec, int $index): string
    {
        return 'Chuẩn đầu ra CLO'.$index.' dùng để mapping câu hỏi, blueprint và mock exam cho '.$spec['name'].'.';
    }

    private function durationFor(array $spec): int
    {
        return match ($spec['group']) {
            'TOEIC' => 120,
            'IELTS', 'TOEFL' => 90,
            'HSK', 'TOPIK' => 75,
            default => 60,
        };
    }

    private function mockTypeFor(array $spec): string
    {
        return in_array($spec['group'], ['TOEIC', 'IELTS', 'TOEFL', 'HSK', 'TOPIK'], true) ? 'mock_exam' : 'practice';
    }
}
