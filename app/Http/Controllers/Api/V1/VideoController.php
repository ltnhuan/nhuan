<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\VideoAsset;
use App\Models\VideoProgressSummary;
use App\Models\VideoWatchSession;
use App\Services\TenantContext;
use App\Services\VideoAssetService;
use App\Services\VideoProcessingService;
use App\Services\VideoTrackingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class VideoController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext)
    {
        return VideoAsset::query()
            ->where('tenant_id', $tenantContext->id())
            ->with(['course:id,title', 'component:id,title', 'renditions'])
            ->when($request->filled('course_id'), fn ($query) => $query->where('course_id', $request->integer('course_id')))
            ->when($request->filled('processing_status'), fn ($query) => $query->where('processing_status', $request->input('processing_status')))
            ->when($request->filled('uploaded_by'), fn ($query) => $query->where('uploaded_by', $request->integer('uploaded_by')))
            ->latest('updated_at')
            ->paginate($request->integer('per_page', 30));
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
        return $video->load(['course:id,title', 'component:id,title', 'renditions']);
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
            ->paginate($request->integer('per_page', 50));
    }

    public function myComponentProgress(Request $request, CourseComponent $component)
    {
        return VideoProgressSummary::query()
            ->where('component_id', $component->id)
            ->where('user_id', $request->user()?->id ?? 1)
            ->with('asset:id,title,duration_seconds')
            ->first();
    }
}
