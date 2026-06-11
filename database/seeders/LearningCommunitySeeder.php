<?php

namespace Database\Seeders;

use App\Models\CommunityNotification;
use App\Models\Course;
use App\Models\DiscussionPost;
use App\Models\LmsUser;
use App\Models\ModerationReport;
use App\Models\ReputationBadge;
use App\Services\LearningCommunityService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LearningCommunitySeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        $teachers = LmsUser::query()->where('tenant_id', $tenantId)->whereIn('user_type', ['teacher', 'staff', 'admin'])->limit(12)->get();
        $students = LmsUser::query()->where('tenant_id', $tenantId)->where('user_type', 'student')->limit(80)->get();
        $courses = Course::query()->where('tenant_id', $tenantId)->limit(12)->get();

        if ($teachers->isEmpty() || $students->isEmpty()) {
            return;
        }

        $this->resetCommunityData($tenantId);
        $this->seedBadges($tenantId);

        $service = app(LearningCommunityService::class);
        $forums = [
            ['course', 'Course Forum - An toàn học tập', 'Hỏi đáp PPE, quy trình lab, checklist trước buổi thực hành.', 7],
            ['course', 'Course Forum - Vận hành thiết bị', 'Tình huống vận hành, lỗi thường gặp, video orientation.', 6],
            ['class', 'Lớp CNK46A', 'Trao đổi bài tập, lịch học, tài liệu theo lớp.', 6],
            ['class', 'Lớp LOG12B', 'Nhóm logistics, case study và phản hồi của giảng viên.', 5],
            ['topic', 'Thực hành mô phỏng', 'Kịch bản mô phỏng, replay phiên thực hành, rubric đánh giá.', 7],
            ['topic', 'Q&A kiểm tra online', 'Hỏi đáp blueprint đề, thời gian làm bài, lỗi kỹ thuật.', 5],
            ['faculty', 'Khoa Công nghệ', 'Thông báo học thuật, mentor cộng đồng và chia sẻ chuyên môn.', 7],
            ['community', 'Cộng đồng kỹ thuật VABIS', 'Không gian kết nối học viên, cựu học viên và giảng viên.', 8],
        ];

        foreach ($forums as $forumIndex => [$type, $title, $description, $threadCount]) {
            $teacher = $teachers[$forumIndex % $teachers->count()];
            $course = $courses->isNotEmpty() ? $courses[$forumIndex % $courses->count()] : null;
            $forum = $service->createForum([
                'tenant_id' => $tenantId,
                'forum_type' => $type,
                'course_id' => in_array($type, ['course', 'topic'], true) ? $course?->id : null,
                'title' => $title,
                'description' => $description,
                'visibility' => $type === 'community' ? 'public' : $type,
                'is_pinned' => $forumIndex < 3,
                'created_by' => $teacher->id,
                'last_activity_at' => now()->subHours($forumIndex + 1),
            ]);

            for ($i = 1; $i <= $threadCount; $i++) {
                $student = $students[($forumIndex * 9 + $i) % $students->count()];
                $threadType = ['qna', 'discussion', 'wiki', 'announcement'][$i % 4];
                $thread = $service->createThread($forum, [
                    'thread_type' => $threadType,
                    'title' => $this->threadTitle($type, $i),
                    'body_html' => '<p>'.$this->threadBody($type, $i).' @'.$teacher->code.'</p>',
                    'is_pinned' => $i <= 2,
                ], $student->id);

                $thread->forceFill([
                    'view_count' => 35 + ($forumIndex * 18) + ($i * 9),
                    'like_count' => 4 + (($forumIndex + $i) % 18),
                    'upvote_count' => 2 + ($i % 7),
                    'last_activity_at' => now()->subHours(($forumIndex * 8) + $i),
                    'created_at' => now()->subDays(($forumIndex % 5) + 1)->subHours($i),
                ])->save();

                $replyTotal = 2 + (($forumIndex + $i) % 3);
                $correctPost = null;
                for ($r = 1; $r <= $replyTotal; $r++) {
                    $author = $r % 3 === 0 ? $teacher : $students[($forumIndex * 17 + $i * 5 + $r) % $students->count()];
                    $post = $service->createPost($thread, [
                        'body_html' => '<p>'.$this->replyBody($r, $type).'</p>',
                    ], $author->id);
                    $post->forceFill([
                        'like_count' => ($r * 2 + $i) % 16,
                        'upvote_count' => ($r + $forumIndex) % 8,
                        'created_at' => now()->subHours(($forumIndex * 8) + $i)->addMinutes($r * 7),
                    ])->save();

                    if ($threadType === 'qna' && $r === 2) {
                        $correctPost = $post;
                    }
                }

                if ($correctPost) {
                    $service->markCorrect($correctPost, $teacher->id);
                }
            }
        }

        $this->seedKnowledgeAndGroups($service, $tenantId, $teachers, $students, $courses);
        $this->seedModerationAndNotifications($tenantId, $teachers, $students);
    }

    private function resetCommunityData(int $tenantId): void
    {
        DB::transaction(function () use ($tenantId) {
            foreach ([
                'community_activity_events',
                'community_notifications',
                'moderation_reports',
                'reputation_events',
                'user_badges',
                'reputation_profiles',
                'community_group_members',
                'community_groups',
                'blog_posts',
                'wiki_pages',
                'discussion_reactions',
                'discussion_mentions',
                'discussion_posts',
                'discussion_threads',
                'community_forums',
            ] as $table) {
                DB::table($table)->delete();
            }
        });
    }

    private function seedBadges(int $tenantId): void
    {
        foreach ([
            ['first_reply', 'Người mở thảo luận', 10],
            ['helpful_answer', 'Câu trả lời hữu ích', 30],
            ['active_member', 'Thành viên tích cực', 75],
            ['mentor', 'Mentor cộng đồng', 150],
            ['knowledge_curator', 'Người đóng góp wiki', 220],
        ] as [$code, $name, $points]) {
            ReputationBadge::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                ['name' => $name, 'description' => $name, 'required_points' => $points, 'criteria' => []]
            );
        }
    }

    private function seedKnowledgeAndGroups(LearningCommunityService $service, int $tenantId, $teachers, $students, $courses): void
    {
        $wikiTitles = [
            'Checklist PPE trước buổi thực hành',
            'Quy trình báo lỗi thiết bị trong lab',
            'Sổ tay project vận hành',
            'Rubric đánh giá thảo luận nhóm',
            'FAQ kiểm tra online',
            'Hướng dẫn trích dẫn nguồn học liệu',
            'Quy ước đặt tên file nộp bài',
            'Template biên bản họp nhóm',
            'Bộ thuật ngữ kỹ thuật căn bản',
            'Quy trình mentor phản hồi câu hỏi',
            'Hướng dẫn dùng forum hiệu quả',
            'Checklist hoàn tất học phần',
        ];

        foreach ($wikiTitles as $index => $title) {
            $teacher = $teachers[$index % $teachers->count()];
            $course = $courses->isNotEmpty() ? $courses[$index % $courses->count()] : null;
            $wiki = $service->createWiki([
                'wiki_type' => $index % 3 === 0 ? 'group' : 'course',
                'course_id' => $course?->id,
                'slug' => Str::slug($title),
                'title' => $title,
                'body_html' => '<p>Nội dung mẫu cho '.$title.', gồm mục tiêu, checklist, tài liệu liên quan và người phụ trách cập nhật.</p>',
                'version' => 1 + ($index % 5),
            ], $teacher->id, $tenantId);
            $wiki->forceFill(['updated_at' => now()->subDays($index % 10)])->save();
        }

        foreach ([
            'Kinh nghiệm học nhóm hiệu quả',
            'Cách đặt câu hỏi tốt trong forum',
            'Tổng kết buổi mô phỏng vận hành',
            'Những lỗi thường gặp khi làm bài online',
            'Chia sẻ tài liệu chuẩn bị thực tập',
            'Mentor phản hồi case study logistics',
            'Cách đọc rubric trước khi nộp bài',
            'Câu chuyện cải thiện điểm danh online',
            'Checklist học viên mới trong LMS',
            'Tối ưu thời gian ôn tập cuối kỳ',
        ] as $index => $title) {
            $author = $index % 2 === 0 ? $students[$index % $students->count()] : $teachers[$index % $teachers->count()];
            $blog = $service->createBlog([
                'blog_type' => $author->user_type === 'student' ? 'student' : 'teacher',
                'course_id' => $courses->isNotEmpty() ? $courses[$index % $courses->count()]?->id : null,
                'title' => $title,
                'body_html' => '<p>Bài viết mẫu có nội dung đủ dài để test danh sách blog, trạng thái xuất bản, lượt thích và bình luận.</p>',
                'status' => $index === 1 ? 'draft' : 'published',
            ], $author->id, $tenantId);
            $blog->forceFill([
                'like_count' => 8 + ($index * 5),
                'comment_count' => 2 + ($index % 6),
                'published_at' => $index === 1 ? null : now()->subDays($index),
            ])->save();
        }

        foreach ([
            ['community', 'Cộng đồng kỹ thuật VABIS', 1240],
            ['study', 'Study Group ca tối', 36],
            ['project', 'Project vận hành thiết bị', 12],
            ['study', 'Nhóm ôn tập kiểm tra online', 58],
            ['project', 'Nhóm case study logistics', 18],
            ['community', 'Mentor hub khoa Công nghệ', 96],
            ['study', 'Nhóm học viên mới', 145],
            ['project', 'Project mô phỏng an toàn', 22],
        ] as $index => [$type, $name, $members]) {
            $group = $service->createGroup([
                'group_type' => $type,
                'course_id' => $courses->isNotEmpty() ? $courses[$index % $courses->count()]?->id : null,
                'name' => $name,
                'description' => 'Dữ liệu mẫu cho '.$name.', dùng để test danh sách nhóm, số thành viên và quyền owner.',
                'visibility' => $type === 'community' ? 'public' : 'members',
            ], $students[$index % $students->count()]->id, $tenantId);
            $group->forceFill(['member_count' => $members])->save();
        }
    }

    private function seedModerationAndNotifications(int $tenantId, $teachers, $students): void
    {
        $posts = DiscussionPost::query()->where('tenant_id', $tenantId)->latest()->limit(16)->get();
        foreach ($posts->take(10) as $index => $post) {
            ModerationReport::query()->create([
                'tenant_id' => $tenantId,
                'reportable_type' => 'post',
                'reportable_id' => $post->id,
                'reported_by' => $students[$index % $students->count()]->id,
                'reason' => ['needs_review', 'off_topic', 'duplicate', 'attachment_check'][$index % 4],
                'note' => 'Báo cáo mẫu để test queue kiểm duyệt #'.($index + 1),
                'status' => $index < 7 ? 'pending' : 'resolved',
                'resolved_by' => $index < 7 ? null : $teachers[$index % $teachers->count()]->id,
                'resolved_at' => $index < 7 ? null : now()->subHours($index),
            ]);
        }

        foreach ($students->take(20) as $index => $student) {
            CommunityNotification::query()->create([
                'tenant_id' => $tenantId,
                'user_id' => $student->id,
                'notification_type' => ['mention', 'reply', 'correct_answer', 'group_invite'][$index % 4],
                'title' => ['Bạn được nhắc đến', 'Có phản hồi mới', 'Câu trả lời được xác nhận', 'Lời mời tham gia nhóm'][$index % 4],
                'body' => 'Thông báo mẫu phục vụ kiểm thử realtime notification trên dashboard community.',
                'payload' => ['demo' => true],
                'read_at' => $index % 3 === 0 ? now()->subHours($index) : null,
                'created_at' => now()->subMinutes($index * 11),
            ]);
        }
    }

    private function threadTitle(string $type, int $index): string
    {
        return match ($type) {
            'course' => 'Hỏi đáp học phần tuần '.$index.' và checklist chuẩn bị',
            'class' => 'Lịch học, bài tập và nhóm hỗ trợ ca '.$index,
            'topic' => 'Tình huống mô phỏng số '.$index.' cần thảo luận',
            'faculty' => 'Thông báo khoa và mentor chuyên đề '.$index,
            default => 'Chủ đề cộng đồng kỹ thuật số '.$index,
        };
    }

    private function threadBody(string $type, int $index): string
    {
        return 'Nội dung mở đầu cho '.$type.' topic '.$index.', có câu hỏi, bối cảnh học tập, tài liệu cần đọc và tiêu chí phản hồi.';
    }

    private function replyBody(int $index, string $type): string
    {
        return 'Phản hồi mẫu '.$index.' cho '.$type.', gồm hướng dẫn cụ thể, liên kết tới wiki/course material và bước tiếp theo để học viên thực hiện.';
    }
}
