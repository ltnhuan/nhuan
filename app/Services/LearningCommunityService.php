<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\CommunityActivityEvent;
use App\Models\CommunityForum;
use App\Models\CommunityGroup;
use App\Models\CommunityGroupMember;
use App\Models\CommunityNotification;
use App\Models\DiscussionMention;
use App\Models\DiscussionPost;
use App\Models\DiscussionReaction;
use App\Models\DiscussionThread;
use App\Models\LmsUser;
use App\Models\ModerationReport;
use App\Models\ReputationBadge;
use App\Models\ReputationEvent;
use App\Models\ReputationProfile;
use App\Models\UserBadge;
use App\Models\WikiPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LearningCommunityService
{
    public function createForum(array $data): CommunityForum
    {
        return CommunityForum::query()->create($data + [
            'visibility' => 'course',
            'status' => 'active',
            'settings' => ['realtime_notifications' => true, 'rich_text_editor' => 'html'],
        ]);
    }

    public function createThread(CommunityForum $forum, array $data, int $userId): DiscussionThread
    {
        return DB::transaction(function () use ($forum, $data, $userId) {
            $thread = DiscussionThread::query()->create([
                'tenant_id' => $forum->tenant_id,
                'forum_id' => $forum->id,
                'thread_type' => $data['thread_type'] ?? 'discussion',
                'title' => $data['title'],
                'excerpt' => Str::limit(strip_tags($data['body_html'] ?? ''), 220),
                'status' => 'open',
                'is_pinned' => (bool) ($data['is_pinned'] ?? false),
                'created_by' => $userId,
                'last_post_by' => $userId,
                'last_activity_at' => now(),
            ]);

            $this->createPost($thread, $data + ['post_type' => 'post'], $userId, false);
            $forum->increment('thread_count');
            $forum->forceFill(['last_activity_at' => now()])->save();
            $this->activity($forum->tenant_id, $userId, 'thread_created', $forum->id, $thread->id);
            $this->awardPoints($forum->tenant_id, $userId, 5, 'thread_created', $thread->id, DiscussionThread::class);

            return $thread->fresh(['forum','posts.author','posts.mentions']);
        });
    }

    public function createPost(DiscussionThread $thread, array $data, int $userId, bool $countReply = true): DiscussionPost
    {
        if ($thread->is_locked) {
            throw new \InvalidArgumentException('Topic đã khóa, không thể trả lời.');
        }

        return DB::transaction(function () use ($thread, $data, $userId, $countReply) {
            $bodyHtml = $data['body_html'] ?? $data['body'] ?? '';
            $post = DiscussionPost::query()->create([
                'tenant_id' => $thread->tenant_id,
                'thread_id' => $thread->id,
                'parent_id' => $data['parent_id'] ?? null,
                'quoted_post_id' => $data['quoted_post_id'] ?? null,
                'user_id' => $userId,
                'post_type' => $data['post_type'] ?? 'reply',
                'body_html' => $bodyHtml,
                'body_text' => trim(strip_tags($bodyHtml)),
                'status' => $data['status'] ?? 'visible',
                'is_pinned' => (bool) ($data['is_pinned'] ?? false),
                'metadata' => ['editor' => 'rich_text', 'quote_enabled' => array_key_exists('quoted_post_id', $data)],
            ]);

            $this->syncMentions($post, $bodyHtml, $userId);
            if ($countReply) {
                $thread->increment('reply_count');
            }
            $thread->forceFill(['last_post_by' => $userId, 'last_activity_at' => now()])->save();
            $thread->forum()->increment('post_count');
            $thread->forum()->update(['last_activity_at' => now()]);
            $this->activity($thread->tenant_id, $userId, 'post_created', $thread->forum_id, $thread->id);
            $this->awardPoints($thread->tenant_id, $userId, $countReply ? 2 : 1, $countReply ? 'reply_created' : 'post_created', $post->id, DiscussionPost::class);

            return $post->fresh(['author','mentions']);
        });
    }

    public function react(DiscussionPost $post, int $userId, string $type = 'like'): DiscussionReaction
    {
        $reaction = DiscussionReaction::query()->firstOrCreate([
            'post_id' => $post->id,
            'user_id' => $userId,
            'reaction_type' => $type,
        ], [
            'tenant_id' => $post->tenant_id,
            'thread_id' => $post->thread_id,
            'created_at' => now(),
        ]);

        if ($reaction->wasRecentlyCreated) {
            $column = $type === 'upvote' ? 'upvote_count' : 'like_count';
            $post->increment($column);
            $post->thread()->increment($column);
            $this->awardPoints($post->tenant_id, $post->user_id, $type === 'upvote' ? 3 : 1, $type.'_received', $post->id, DiscussionPost::class);
        }

        return $reaction;
    }

    public function markCorrect(DiscussionPost $post, int $moderatorId): DiscussionPost
    {
        return DB::transaction(function () use ($post, $moderatorId) {
            DiscussionPost::query()->where('thread_id', $post->thread_id)->update(['is_correct_answer' => false]);
            $post->forceFill(['is_correct_answer' => true])->save();
            $post->thread()->update(['correct_post_id' => $post->id]);
            $this->awardPoints($post->tenant_id, $post->user_id, 10, 'correct_answer', $post->id, DiscussionPost::class);
            $this->notify($post->tenant_id, $post->user_id, 'correct_answer', 'Câu trả lời được đánh dấu đúng', 'Câu trả lời của bạn đã được xác nhận.', ['post_id' => $post->id]);
            $this->activity($post->tenant_id, $moderatorId, 'correct_answer_marked', $post->thread->forum_id, $post->thread_id);

            return $post->fresh();
        });
    }

    public function moderate(string $type, int $id, string $action, int $moderatorId, ?string $reason = null): array
    {
        $model = $type === 'thread' ? DiscussionThread::query()->findOrFail($id) : DiscussionPost::query()->findOrFail($id);
        if ($action === 'approve') {
            $model->forceFill(['status' => $type === 'thread' ? 'open' : 'visible'])->save();
        } elseif ($action === 'hide') {
            $model->forceFill(['status' => 'hidden'])->save();
        } elseif ($action === 'lock' && $model instanceof DiscussionThread) {
            $model->forceFill(['is_locked' => true, 'status' => 'locked'])->save();
        } elseif ($action === 'pin') {
            $model->forceFill(['is_pinned' => true])->save();
        } else {
            throw new \InvalidArgumentException('Hành động moderation không hợp lệ.');
        }

        $this->activity($model->tenant_id, $moderatorId, 'moderation_'.$action, $model instanceof DiscussionThread ? $model->forum_id : $model->thread->forum_id, $model instanceof DiscussionThread ? $model->id : $model->thread_id);
        return ['status' => $model->status, 'is_locked' => (bool) ($model->is_locked ?? false), 'is_pinned' => (bool) ($model->is_pinned ?? false), 'reason' => $reason];
    }

    public function report(string $type, int $id, array $data, int $userId, int $tenantId): ModerationReport
    {
        return ModerationReport::query()->create([
            'tenant_id' => $tenantId,
            'reportable_type' => $type,
            'reportable_id' => $id,
            'reported_by' => $userId,
            'reason' => $data['reason'] ?? 'other',
            'note' => $data['note'] ?? null,
        ]);
    }

    public function createWiki(array $data, int $userId, int $tenantId): WikiPage
    {
        return WikiPage::query()->create($data + ['tenant_id' => $tenantId, 'created_by' => $userId, 'updated_by' => $userId, 'version' => 1, 'status' => 'published']);
    }

    public function updateWiki(WikiPage $page, array $data, int $userId): WikiPage
    {
        $page->fill($data + ['updated_by' => $userId]);
        $page->version++;
        $page->save();
        return $page;
    }

    public function createBlog(array $data, int $userId, int $tenantId): BlogPost
    {
        return BlogPost::query()->create($data + [
            'tenant_id' => $tenantId,
            'author_id' => $userId,
            'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(5)),
            'status' => $data['status'] ?? 'draft',
            'published_at' => ($data['status'] ?? 'draft') === 'published' ? now() : null,
        ]);
    }

    public function createGroup(array $data, int $userId, int $tenantId): CommunityGroup
    {
        return DB::transaction(function () use ($data, $userId, $tenantId) {
            $group = CommunityGroup::query()->create($data + ['tenant_id' => $tenantId, 'owner_id' => $userId, 'settings' => ['wiki_enabled' => true, 'project_space' => true]]);
            CommunityGroupMember::query()->create(['tenant_id' => $tenantId, 'group_id' => $group->id, 'user_id' => $userId, 'role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
            $group->increment('member_count');
            return $group->fresh('members');
        });
    }

    public function aiSummary(DiscussionThread $thread): array
    {
        $posts = $thread->posts()->where('status', 'visible')->orderBy('created_at')->limit(20)->get();
        $keywords = $posts->flatMap(fn (DiscussionPost $post) => str($post->body_text)->lower()->matchAll('/[\pL\pN]{4,}/u')->all())->countBy()->sortDesc()->take(6)->keys()->values()->all();
        return [
            'summary' => 'Topic có '.$posts->count().' bài viết, trọng tâm: '.implode(', ', $keywords).'.',
            'key_points' => $posts->take(3)->map(fn (DiscussionPost $post) => Str::limit($post->body_text ?: strip_tags($post->body_html), 120))->values(),
            'suggested_answer' => 'Bạn có thể trả lời bằng cách xác nhận mục tiêu học tập, nêu ví dụ thực hành, rồi liên kết tới tài liệu/wiki liên quan.',
        ];
    }

    public function analytics(int $tenantId, ?int $courseId = null): array
    {
        $forumIds = CommunityForum::query()->where('tenant_id', $tenantId)->when($courseId, fn ($q) => $q->where('course_id', $courseId))->pluck('id');
        $threadIds = DiscussionThread::query()->whereIn('forum_id', $forumIds)->pluck('id');
        $activeUsers = CommunityActivityEvent::query()->where('tenant_id', $tenantId)->where('created_at', '>=', now()->subDays(30))->distinct('user_id')->count('user_id');
        $activeTopics = DiscussionThread::query()->whereIn('id', $threadIds)->where('last_activity_at', '>=', now()->subDays(30))->count();
        $posts = DiscussionPost::query()->whereIn('thread_id', $threadIds)->count();
        $reactions = DiscussionReaction::query()->whereIn('thread_id', $threadIds)->count();

        return [
            'active_users' => $activeUsers,
            'active_topics' => $activeTopics,
            'engagement_score' => round(($posts * 2) + $reactions + ($activeUsers * 1.5), 2),
            'total_forums' => $forumIds->count(),
            'total_posts' => $posts,
            'total_reactions' => $reactions,
        ];
    }

    private function syncMentions(DiscussionPost $post, string $bodyHtml, int $mentionedBy): void
    {
        preg_match_all('/@([A-Za-z0-9._-]+)/', strip_tags($bodyHtml), $matches);
        $tokens = array_unique($matches[1] ?? []);
        if ($tokens === []) {
            return;
        }

        LmsUser::query()
            ->where('tenant_id', $post->tenant_id)
            ->where(function ($query) use ($tokens) {
                $query->whereIn('code', $tokens)->orWhereIn('email', $tokens);
            })
            ->get()
            ->each(function (LmsUser $user) use ($post, $mentionedBy) {
                DiscussionMention::query()->firstOrCreate([
                    'post_id' => $post->id,
                    'mentioned_user_id' => $user->id,
                ], [
                    'tenant_id' => $post->tenant_id,
                    'mentioned_by' => $mentionedBy,
                    'created_at' => now(),
                ]);
                $this->notify($post->tenant_id, $user->id, 'mention', 'Bạn được nhắc đến', 'Một thành viên đã mention bạn trong thảo luận.', ['post_id' => $post->id, 'thread_id' => $post->thread_id]);
            });
    }

    private function awardPoints(int $tenantId, int $userId, int $points, string $eventType, int $sourceId, string $sourceType): void
    {
        ReputationEvent::query()->create(['tenant_id' => $tenantId, 'user_id' => $userId, 'points' => $points, 'event_type' => $eventType, 'source_id' => $sourceId, 'source_type' => $sourceType, 'created_at' => now()]);
        $profile = ReputationProfile::query()->firstOrCreate(['tenant_id' => $tenantId, 'user_id' => $userId], ['points' => 0, 'rank' => 'new_member']);
        $profile->increment('points', $points);
        $profile->forceFill(['rank' => $this->rankFor((int) $profile->fresh()->points)])->save();
        ReputationBadge::query()->where('tenant_id', $tenantId)->where('required_points', '<=', $profile->points)->get()->each(function (ReputationBadge $badge) use ($tenantId, $userId, $profile) {
            $awarded = UserBadge::query()->firstOrCreate(['user_id' => $userId, 'badge_id' => $badge->id], ['tenant_id' => $tenantId, 'awarded_at' => now()]);
            if ($awarded->wasRecentlyCreated) {
                $profile->increment('badge_count');
            }
        });
    }

    private function rankFor(int $points): string
    {
        return match (true) {
            $points >= 500 => 'mentor',
            $points >= 150 => 'contributor',
            $points >= 50 => 'active_member',
            default => 'new_member',
        };
    }

    private function notify(int $tenantId, int $userId, string $type, string $title, string $body, array $payload = []): void
    {
        CommunityNotification::query()->create(['tenant_id' => $tenantId, 'user_id' => $userId, 'notification_type' => $type, 'title' => $title, 'body' => $body, 'payload' => $payload]);
    }

    private function activity(int $tenantId, ?int $userId, string $type, ?int $forumId = null, ?int $threadId = null): void
    {
        CommunityActivityEvent::query()->create(['tenant_id' => $tenantId, 'user_id' => $userId, 'event_type' => $type, 'forum_id' => $forumId, 'thread_id' => $threadId, 'created_at' => now()]);
    }
}
