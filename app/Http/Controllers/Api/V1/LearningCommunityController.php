<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\BlogPost;
use App\Models\CommunityForum;
use App\Models\CommunityGroup;
use App\Models\CommunityNotification;
use App\Models\DiscussionPost;
use App\Models\DiscussionThread;
use App\Models\LmsUser;
use App\Models\ModerationReport;
use App\Models\ReputationProfile;
use App\Models\WikiPage;
use App\Services\LearningCommunityService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LearningCommunityController extends Controller
{
    public function forums(Request $request, TenantContext $tenant)
    {
        return CommunityForum::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->filled('forum_type'), fn ($q) => $q->where('forum_type', $request->input('forum_type')))
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->integer('course_id')))
            ->withCount('threads')
            ->orderByDesc('is_pinned')
            ->latest('last_activity_at')
            ->paginate($request->integer('per_page', 25));
    }

    public function storeForum(Request $request, TenantContext $tenant, LearningCommunityService $service)
    {
        return response()->json($service->createForum($request->all() + ['tenant_id' => $tenant->id(), 'created_by' => $this->userId($request, (int) $tenant->id())]), 201);
    }

    public function threads(Request $request, CommunityForum $forum)
    {
        return DiscussionThread::query()
            ->where('forum_id', $forum->id)
            ->when($request->filled('thread_type'), fn ($q) => $q->where('thread_type', $request->input('thread_type')))
            ->with(['creator:id,code,full_name,email'])
            ->orderByDesc('is_pinned')
            ->latest('last_activity_at')
            ->paginate($request->integer('per_page', 25));
    }

    public function storeThread(Request $request, CommunityForum $forum, LearningCommunityService $service)
    {
        return response()->json($service->createThread($forum, $request->all(), $this->userId($request, $forum->tenant_id)), 201);
    }

    public function showThread(DiscussionThread $thread)
    {
        $thread->increment('view_count');
        return $thread->load(['forum','creator:id,code,full_name,email','posts.author:id,code,full_name,email','posts.quote:id,body_text,user_id','posts.mentions']);
    }

    public function reply(Request $request, DiscussionThread $thread, LearningCommunityService $service)
    {
        return response()->json($service->createPost($thread, $request->all(), $this->userId($request, $thread->tenant_id)), 201);
    }

    public function react(Request $request, DiscussionPost $post, LearningCommunityService $service)
    {
        return response()->json($service->react($post, $this->userId($request, $post->tenant_id), $request->input('reaction_type', 'like')), 201);
    }

    public function markCorrect(Request $request, DiscussionPost $post, LearningCommunityService $service)
    {
        return $service->markCorrect($post, $this->userId($request, $post->tenant_id));
    }

    public function moderate(Request $request, LearningCommunityService $service)
    {
        return $service->moderate($request->input('type'), (int) $request->input('id'), $request->input('action'), $this->userId($request, (int) $request->input('tenant_id', 1)), $request->input('reason'));
    }

    public function report(Request $request, TenantContext $tenant, LearningCommunityService $service)
    {
        return response()->json($service->report($request->input('type'), (int) $request->input('id'), $request->all(), $this->userId($request, (int) $tenant->id()), (int) $tenant->id()), 201);
    }

    public function reports(Request $request, TenantContext $tenant)
    {
        return ModerationReport::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate($request->integer('per_page', 25));
    }

    public function wikis(Request $request, TenantContext $tenant)
    {
        return WikiPage::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->filled('wiki_type'), fn ($q) => $q->where('wiki_type', $request->input('wiki_type')))
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->integer('course_id')))
            ->latest('updated_at')
            ->paginate($request->integer('per_page', 25));
    }

    public function storeWiki(Request $request, TenantContext $tenant, LearningCommunityService $service)
    {
        return response()->json($service->createWiki($request->all(), $this->userId($request, (int) $tenant->id()), (int) $tenant->id()), 201);
    }

    public function updateWiki(Request $request, WikiPage $wiki, LearningCommunityService $service)
    {
        return $service->updateWiki($wiki, $request->all(), $this->userId($request, $wiki->tenant_id));
    }

    public function blogs(Request $request, TenantContext $tenant)
    {
        return BlogPost::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->filled('blog_type'), fn ($q) => $q->where('blog_type', $request->input('blog_type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('published_at')
            ->paginate($request->integer('per_page', 25));
    }

    public function storeBlog(Request $request, TenantContext $tenant, LearningCommunityService $service)
    {
        return response()->json($service->createBlog($request->all(), $this->userId($request, (int) $tenant->id()), (int) $tenant->id()), 201);
    }

    public function groups(Request $request, TenantContext $tenant)
    {
        return CommunityGroup::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->filled('group_type'), fn ($q) => $q->where('group_type', $request->input('group_type')))
            ->latest()
            ->paginate($request->integer('per_page', 25));
    }

    public function storeGroup(Request $request, TenantContext $tenant, LearningCommunityService $service)
    {
        return response()->json($service->createGroup($request->all(), $this->userId($request, (int) $tenant->id()), (int) $tenant->id()), 201);
    }

    public function notifications(Request $request, TenantContext $tenant)
    {
        return CommunityNotification::query()
            ->where('tenant_id', $tenant->id())
            ->where('user_id', $this->userId($request, (int) $tenant->id()))
            ->latest()
            ->paginate($request->integer('per_page', 25));
    }

    public function reputation(Request $request, TenantContext $tenant)
    {
        return ReputationProfile::query()
            ->where('tenant_id', $tenant->id())
            ->withCount([])
            ->orderByDesc('points')
            ->paginate($request->integer('per_page', 25));
    }

    public function ai(DiscussionThread $thread, LearningCommunityService $service)
    {
        return $service->aiSummary($thread);
    }

    public function analytics(Request $request, TenantContext $tenant, LearningCommunityService $service)
    {
        return $service->analytics((int) $tenant->id(), $request->filled('course_id') ? $request->integer('course_id') : null);
    }

    private function userId(Request $request, int $tenantId): int
    {
        if ($request->user()) {
            return (int) $request->user()->id;
        }
        if ($request->header('X-Demo-User-Email')) {
            return (int) (LmsUser::query()->where('tenant_id', $tenantId)->where('email', $request->header('X-Demo-User-Email'))->value('id') ?: 1);
        }
        return 1;
    }
}
