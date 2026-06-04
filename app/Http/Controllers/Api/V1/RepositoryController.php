<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ContentRepositoryItem;
use App\Services\ApprovalWorkflowService;
use App\Services\RepositoryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class RepositoryController extends Controller
{
    public function index(Request $request)
    {
        return ContentRepositoryItem::query()->where('tenant_id', $request->attributes->get('tenant')?->id)->where('parent_id', $request->query('parent_id'))->paginate(50);
    }

    public function storeFolder(Request $request, RepositoryService $repository)
    {
        return $repository->createFolder($request->all() + ['tenant_id' => $request->attributes->get('tenant')->id, 'owner_id' => $request->user()?->id ?? 1]);
    }

    public function upload(Request $request, RepositoryService $repository)
    {
        return $repository->uploadFile($request->except('file') + ['tenant_id' => $request->attributes->get('tenant')->id, 'owner_id' => $request->user()?->id ?? 1], $request->file('file'));
    }

    public function update(Request $request, ContentRepositoryItem $item)
    {
        $item->fill($request->all())->save(); return $item;
    }

    public function newVersion(Request $request, ContentRepositoryItem $item, RepositoryService $repository)
    {
        return $repository->newVersion($item, $request->file('file'), $request->user()?->id ?? 1, $request->input('change_note'));
    }

    public function move(Request $request, ContentRepositoryItem $item, RepositoryService $repository)
    {
        return $repository->move($item, $request->input('parent_id'));
    }

    public function copy(Request $request, ContentRepositoryItem $item, RepositoryService $repository)
    {
        return $repository->copy($item, $request->user()?->id ?? 1);
    }

    public function submitReview(Request $request, ContentRepositoryItem $item, ApprovalWorkflowService $approval)
    {
        return $approval->submitReview($item, $request->user()?->id ?? 1, $request->input('note'));
    }

    public function approve(Request $request, ContentRepositoryItem $item, ApprovalWorkflowService $approval)
    {
        return $approval->approve($item, $request->user()?->id ?? 1, $request->input('note'));
    }
}
