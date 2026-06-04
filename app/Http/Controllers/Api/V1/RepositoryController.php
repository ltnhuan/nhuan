<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ContentRepositoryItem;
use App\Services\ApprovalWorkflowService;
use App\Services\RepositoryService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RepositoryController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext)
    {
        return ContentRepositoryItem::query()
            ->where('tenant_id', $tenantContext->id())
            ->where('parent_id', $request->query('parent_id'))
            ->with(['owner:id,full_name', 'academicUnit:id,name'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = (string) $request->string('search');
                $query->where('title', 'like', "%{$search}%");
            })
            ->when($request->filled('item_type'), fn ($query) => $query->where('item_type', $request->input('item_type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('visibility'), fn ($query) => $query->where('visibility', $request->input('visibility')))
            ->orderByRaw("case when item_type = 'folder' then 0 else 1 end")
            ->orderBy('title')
            ->paginate($request->integer('per_page', 50));
    }

    public function tree(TenantContext $tenantContext)
    {
        return ContentRepositoryItem::query()
            ->where('tenant_id', $tenantContext->id())
            ->where('item_type', 'folder')
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('title')
            ->get();
    }

    public function show(ContentRepositoryItem $item)
    {
        return $item->load(['parent', 'children', 'versions.creator', 'owner', 'academicUnit', 'components.course']);
    }

    public function storeFolder(Request $request, RepositoryService $repository, TenantContext $tenantContext)
    {
        return response()->json($repository->createFolder($request->validate([
            'parent_id' => ['nullable', 'integer'],
            'academic_unit_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['nullable', 'in:private,faculty,tenant,public'],
            'metadata' => ['nullable', 'array'],
        ]) + ['tenant_id' => $tenantContext->id(), 'owner_id' => $request->user()?->id ?? 1]), 201);
    }

    public function upload(Request $request, RepositoryService $repository, TenantContext $tenantContext)
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer'],
            'academic_unit_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'item_type' => ['nullable', 'string'],
            'visibility' => ['nullable', 'in:private,faculty,tenant,public'],
            'metadata' => ['nullable', 'array'],
            'file' => ['required', 'file'],
        ]);

        return response()->json($repository->uploadFile(collect($data)->except('file')->all() + ['tenant_id' => $tenantContext->id(), 'owner_id' => $request->user()?->id ?? 1], $request->file('file')), 201);
    }

    public function update(Request $request, ContentRepositoryItem $item)
    {
        $item->fill($request->validate([
            'parent_id' => ['nullable', 'integer'],
            'academic_unit_id' => ['nullable', 'integer'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['nullable', 'in:private,faculty,tenant,public'],
            'status' => ['nullable', 'in:draft,review,approved,published,archived'],
            'metadata' => ['nullable', 'array'],
        ]))->save();

        return $item->fresh(['parent', 'versions']);
    }

    public function newVersion(Request $request, ContentRepositoryItem $item, RepositoryService $repository)
    {
        $request->validate(['file' => ['required', 'file'], 'change_note' => ['nullable', 'string']]);

        return $repository->newVersion($item, $request->file('file'), $request->user()?->id ?? 1, $request->input('change_note'));
    }

    public function move(Request $request, ContentRepositoryItem $item, RepositoryService $repository)
    {
        return $repository->move($item, $request->integer('parent_id') ?: null);
    }

    public function copy(Request $request, ContentRepositoryItem $item, RepositoryService $repository)
    {
        return $repository->copy($item, $request->user()?->id ?? 1, $request->integer('parent_id') ?: null);
    }

    public function share(Request $request, ContentRepositoryItem $item, RepositoryService $repository)
    {
        return $repository->share($item, $request->validate([
            'visibility' => ['required', 'in:private,faculty,tenant,public'],
            'tenant_id' => ['nullable', 'integer'],
            'academic_unit_id' => ['nullable', 'integer'],
            'course_id' => ['nullable', 'integer'],
        ]));
    }

    public function versions(ContentRepositoryItem $item)
    {
        return $item->versions()->with('creator:id,full_name')->paginate(20);
    }

    public function downloadUrl(ContentRepositoryItem $item, RepositoryService $repository)
    {
        return ['url' => $repository->signedDownloadUrl($item), 'expires_in_minutes' => 30];
    }

    public function submitReview(Request $request, ContentRepositoryItem $item, ApprovalWorkflowService $approval)
    {
        return $approval->submitReview($item, $request->user()?->id ?? 1, $request->input('note'));
    }

    public function approve(Request $request, ContentRepositoryItem $item, ApprovalWorkflowService $approval)
    {
        return $approval->approve($item, $request->user()?->id ?? 1, $request->input('note'));
    }

    public function reject(Request $request, ContentRepositoryItem $item, ApprovalWorkflowService $approval)
    {
        return $approval->reject($item, $request->user()?->id ?? 1, $request->input('note'));
    }

    public function returnForEdit(Request $request, ContentRepositoryItem $item, ApprovalWorkflowService $approval)
    {
        return $approval->returnForEdit($item, $request->user()?->id ?? 1, $request->input('note'));
    }
}
