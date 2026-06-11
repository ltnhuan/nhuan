<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\QuestionBank;
use App\Models\VideoAsset;
use App\Models\VideoProgressSummary;
use App\Models\VideoWatchSession;
use App\Services\TenantContext;
use App\Services\VideoAssetService;
use App\Services\VideoProcessingService;
use App\Services\VideoTrackingService;
use App\Support\ApiPagination;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class VideoController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext)
    {
        return VideoAsset::query()
            ->where('tenant_id', $tenantContext->id())
            ->with(['course:id,title', 'component:id,title', 'renditions'])
            ->when($request->filled('course_id'), fn ($query) => $query->where('course_id', $request->integer('course_id')))
            ->when($request->filled('processing_status'), fn ($query) => $query->where('processing_status', $request->input('processing_status')))
            ->when($request->filled('visibility'), fn ($query) => $query->where('visibility', $request->input('visibility')))
            ->when($request->filled('uploaded_by'), fn ($query) => $query->where('uploaded_by', $request->integer('uploaded_by')))
            ->when($request->filled('q'), fn ($query) => $query->where(function ($search) use ($request) {
                $term = '%'.$request->input('q').'%';
                $search->where('title', 'like', $term)->orWhere('description', 'like', $term)->orWhere('original_filename', 'like', $term);
            }))
            ->latest('updated_at')
            ->paginate(ApiPagination::perPage($request, 30))
            ->through(fn (VideoAsset $asset) => $this->decorateVideoUsage($asset));
    }

    public function upload(Request $request, VideoAssetService $videos, TenantContext $tenantContext)
    {
        $data = $request->validate([
            'course_id' => ['nullable', 'integer'],
            'component_id' => ['nullable', 'integer'],
            'repository_item_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['nullable', 'in:private,course,tenant,public'],
            'settings' => ['nullable', 'array'],
            'file' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska'],
        ]);

        return response()->json($videos->uploadVideo($data + ['tenant_id' => $tenantContext->id(), 'uploaded_by' => $request->user()?->id ?? 1], $request->file('file')), 201);
    }

    public function show(VideoAsset $video)
    {
        return $video->load(['course:id,title', 'component:id,title', 'renditions'])
            ->loadCount(['renditions'])
            ->setAttribute('usage', $this->videoUsage($video))
            ->setAttribute('analytics', $this->videoAnalytics($video));
    }

    public function update(Request $request, VideoAsset $video)
    {
        $data = $request->validate([
            'course_id' => ['nullable', 'integer'],
            'component_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'processing_status' => ['nullable', 'in:pending,processing,ready,failed,archived'],
            'visibility' => ['nullable', 'in:private,course,tenant,public'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'subtitle_path' => ['nullable', 'string', 'max:2048'],
            'transcript_path' => ['nullable', 'string', 'max:2048'],
            'settings' => ['nullable', 'array'],
        ]);

        $video->fill($data)->save();

        return $this->decorateVideoUsage($video->fresh(['course:id,title', 'component:id,title', 'renditions']));
    }

    public function destroy(VideoAsset $video)
    {
        DB::transaction(function () use ($video) {
            DB::table('video_watch_events')->where('video_asset_id', $video->id)->delete();
            VideoWatchSession::query()->where('video_asset_id', $video->id)->delete();
            VideoProgressSummary::query()->where('video_asset_id', $video->id)->delete();
            $video->renditions()->delete();
            $video->delete();
        });

        return response()->noContent();
    }

    public function analytics(Request $request, TenantContext $tenantContext)
    {
        $tenantId = $tenantContext->id();
        $assetQuery = VideoAsset::query()->where('tenant_id', $tenantId)
            ->when($request->filled('course_id'), fn ($query) => $query->where('course_id', $request->integer('course_id')));
        $summaryQuery = VideoProgressSummary::query()->where('tenant_id', $tenantId)
            ->when($request->filled('course_id'), fn ($query) => $query->where('course_id', $request->integer('course_id')));

        $totalSummaries = (clone $summaryQuery)->count();
        $completed = (clone $summaryQuery)->where('is_completed', true)->count();
        $below50 = (clone $summaryQuery)->where('watch_percent', '<', 50)->count();
        $suspicious = (clone $summaryQuery)->where('suspicious_score', '>=', 60)->count();

        $topVideos = (clone $summaryQuery)
            ->select('video_asset_id', DB::raw('count(*) as learners'), DB::raw('avg(watch_percent) as avg_watch'), DB::raw('sum(case when is_completed then 1 else 0 end) as completed_count'), DB::raw('max(suspicious_score) as max_suspicious'))
            ->with('asset:id,title,duration_seconds,processing_status,visibility')
            ->groupBy('video_asset_id')
            ->orderByDesc('learners')
            ->limit(10)
            ->get();

        $topVideoAssets = VideoAsset::query()
            ->whereIn('id', $topVideos->pluck('video_asset_id')->filter()->unique()->values())
            ->with(['course:id,title', 'component:id,title'])
            ->get()
            ->keyBy('id');

        $topVideos->transform(function (VideoProgressSummary $item) use ($topVideoAssets) {
            $asset = $topVideoAssets->get((int) $item->video_asset_id);
            if ($asset) {
                $item->usage = $this->videoUsage($asset);
                $item->asset = $asset;
            }
            return $item;
        });

        return [
            'summary' => [
                'videos' => (clone $assetQuery)->count(),
                'ready' => (clone $assetQuery)->where('processing_status', 'ready')->count(),
                'processing' => (clone $assetQuery)->whereIn('processing_status', ['pending', 'processing'])->count(),
                'failed' => (clone $assetQuery)->where('processing_status', 'failed')->count(),
                'learners_tracked' => (clone $summaryQuery)->distinct('user_id')->count('user_id'),
                'completion_rate' => $totalSummaries ? round($completed * 100 / $totalSummaries, 1) : 0,
                'avg_watch_percent' => round((float) (clone $summaryQuery)->avg('watch_percent'), 1),
                'below_50' => $below50,
                'completed' => $completed,
                'suspicious_high' => $suspicious,
            ],
            'progress' => (clone $summaryQuery)
                ->with(['asset:id,title,duration_seconds,processing_status,visibility'])
                ->orderByDesc('last_watched_at')
                ->limit(100)
                ->get(),
            'top_videos' => $topVideos,
        ];
    }

    public function attachComponent(Request $request, VideoAsset $video, VideoAssetService $videos)
    {
        $data = $request->validate(['component_id' => ['required', 'integer']]);
        $component = CourseComponent::query()->findOrFail($data['component_id']);

        return $videos->attachVideoToComponent($video, $component);
    }

    public function process(VideoAsset $video, VideoProcessingService $processing)
    {
        $processing->dispatchProcessingJob($video);

        return ['status' => 'queued', 'video_id' => $video->id];
    }

    public function playbackUrl(VideoAsset $video, VideoAssetService $videos)
    {
        return $videos->generateSignedPlaybackUrl($video);
    }

    public function startSession(Request $request, VideoTrackingService $tracking)
    {
        $data = $request->validate(['video_asset_id' => ['required', 'integer']]);
        $asset = VideoAsset::query()->findOrFail($data['video_asset_id']);

        return response()->json($tracking->startSession($asset, $request->user()?->id ?? 1, $request), 201);
    }

    public function event(Request $request, VideoWatchSession $session, VideoTrackingService $tracking)
    {
        $data = $request->validate([
            'event_type' => ['required', 'string'],
            'position_seconds' => ['required', 'numeric'],
            'watched_delta_seconds' => ['nullable', 'numeric'],
            'playback_rate' => ['nullable', 'numeric'],
            'metadata' => ['nullable', 'array'],
        ]);

        return $tracking->recordEvent($session, $data['event_type'], (float) $data['position_seconds'], (float) ($data['watched_delta_seconds'] ?? 0), (float) ($data['playback_rate'] ?? 1), $data['metadata'] ?? []);
    }

    public function heartbeat(Request $request, VideoWatchSession $session, VideoTrackingService $tracking)
    {
        $data = $request->validate([
            'position_seconds' => ['required', 'numeric'],
            'watched_delta_seconds' => ['required', 'numeric'],
            'playback_rate' => ['nullable', 'numeric'],
            'metadata' => ['nullable', 'array'],
        ]);

        return $tracking->recordHeartbeat($session, (float) $data['position_seconds'], (float) $data['watched_delta_seconds'], (float) ($data['playback_rate'] ?? 1), $data['metadata'] ?? []);
    }

    public function endSession(Request $request, VideoWatchSession $session, VideoTrackingService $tracking)
    {
        return $tracking->endSession($session, (float) $request->input('position_seconds', $session->last_position_seconds));
    }

    public function courseProgress(Request $request, Course $course)
    {
        return VideoProgressSummary::query()
            ->where('course_id', $course->id)
            ->with('asset:id,title,duration_seconds')
            ->when($request->filled('completed'), fn ($query) => $query->where('is_completed', $request->boolean('completed')))
            ->orderByDesc('last_watched_at')
            ->paginate(ApiPagination::perPage($request, 50));
    }

    public function myComponentProgress(Request $request, CourseComponent $component)
    {
        return VideoProgressSummary::query()
            ->where('component_id', $component->id)
            ->where('user_id', $request->user()?->id ?? 1)
            ->with('asset:id,title,duration_seconds')
            ->first();
    }

    private function videoAnalytics(VideoAsset $video): array
    {
        $query = VideoProgressSummary::query()->where('video_asset_id', $video->id);
        $total = (clone $query)->count();
        $completed = (clone $query)->where('is_completed', true)->count();

        return [
            'learners' => $total,
            'completed' => $completed,
            'completion_rate' => $total ? round($completed * 100 / $total, 1) : 0,
            'avg_watch_percent' => round((float) (clone $query)->avg('watch_percent'), 1),
            'suspicious_high' => (clone $query)->where('suspicious_score', '>=', 60)->count(),
            'sessions' => VideoWatchSession::query()->where('video_asset_id', $video->id)->count(),
        ];
    }

    private function decorateVideoUsage(VideoAsset $asset): VideoAsset
    {
        return $asset->setAttribute('usage', $this->videoUsage($asset));
    }

    private function videoUsage(VideoAsset $video): array
    {
        $settings = $video->settings ?? [];
        $bankIds = collect($settings['question_bank_ids'] ?? $settings['linked_question_banks'] ?? [])
            ->filter()
            ->unique()
            ->values();

        $banks = $bankIds->isEmpty()
            ? collect()
            : QuestionBank::query()
                ->whereIn('id', $bankIds)
                ->get(['id', 'code', 'name', 'course_id', 'status']);

        return [
            'course' => $video->course ? ['id' => $video->course->id, 'title' => $video->course->title] : null,
            'component' => $video->component ? ['id' => $video->component->id, 'title' => $video->component->title] : null,
            'question_banks' => $banks->map(fn (QuestionBank $bank) => [
                'id' => $bank->id,
                'code' => $bank->code,
                'name' => $bank->name,
                'status' => $bank->status,
            ])->values(),
            'exam_blueprints' => $settings['exam_blueprints'] ?? [],
            'learning_outcomes' => $settings['learning_outcomes'] ?? [],
            'source_profile' => $settings['source_profile'] ?? ($settings['source_type'] ?? 'uploaded_file'),
            'encoding_profile' => $settings['encoding_profile'] ?? 'adaptive-hls',
        ];
    }
}
