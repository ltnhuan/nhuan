<?php

namespace App\Http\Controllers\Api\V1\Core;

use App\Models\LmsUser;
use App\Services\AuditLogService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext)
    {
        return LmsUser::query()
            ->where('tenant_id', $tenantContext->id())
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = (string) $request->string('search');
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('user_type'), fn ($query) => $query->where('user_type', $request->input('user_type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderBy('code')
            ->paginate($request->integer('per_page', 25));
    }

    public function store(Request $request, TenantContext $tenantContext, AuditLogService $auditLog)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'user_type' => ['required', 'in:student,teacher,staff,admin,parent,external'],
            'status' => ['nullable', 'in:active,inactive,locked,graduated,reserved'],
            'metadata' => ['nullable', 'array'],
        ]);

        $user = LmsUser::query()->create($data + [
            'tenant_id' => $tenantContext->id(),
            'status' => $data['status'] ?? 'active',
            'metadata' => $data['metadata'] ?? [],
        ]);

        $auditLog->record('create', 'core.users', $user, [], $user->toArray(), $request->user(), $request);

        return response()->json($user, 201);
    }

    public function update(Request $request, LmsUser $user, AuditLogService $auditLog)
    {
        $before = $user->toArray();
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
            'user_type' => ['sometimes', 'in:student,teacher,staff,admin,parent,external'],
            'status' => ['sometimes', 'in:active,inactive,locked,graduated,reserved'],
            'metadata' => ['nullable', 'array'],
        ]);

        $user->fill($data)->save();
        $auditLog->record('update', 'core.users', $user, $before, $user->fresh()->toArray(), $request->user(), $request);

        return $user;
    }

    public function lock(Request $request, LmsUser $user, AuditLogService $auditLog)
    {
        $before = $user->toArray();
        $user->forceFill(['status' => 'locked'])->save();
        $auditLog->record('lock', 'core.users', $user, $before, $user->fresh()->toArray(), $request->user(), $request);

        return $user;
    }

    public function unlock(Request $request, LmsUser $user, AuditLogService $auditLog)
    {
        $before = $user->toArray();
        $user->forceFill(['status' => 'active'])->save();
        $auditLog->record('unlock', 'core.users', $user, $before, $user->fresh()->toArray(), $request->user(), $request);

        return $user;
    }
}
