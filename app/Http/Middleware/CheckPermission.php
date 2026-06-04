<?php

namespace App\Http\Middleware;

use App\Models\LmsUser;
use App\Services\Core\CorePermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(private readonly CorePermissionService $permissions)
    {
    }

    public function handle(Request $request, Closure $next, string $permission, string $scope = 'tenant')
    {
        $tenant = $request->attributes->get('tenant');
        $user = $request->user();

        if (! $user && $request->header('X-Demo-User-Email')) {
            $user = LmsUser::query()
                ->where('tenant_id', $tenant?->id)
                ->where('email', $request->header('X-Demo-User-Email'))
                ->first();
        }

        if ($user && ! $user instanceof LmsUser) {
            $user = LmsUser::query()
                ->where('tenant_id', $tenant?->id)
                ->where(function ($query) use ($user) {
                    $query->where('email', $user->email ?? null)
                        ->orWhere('id', method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : ($user->id ?? null));
                })
                ->first();
        }

        if (! $user instanceof LmsUser) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $scopePayload = [
            'tenant_id' => $tenant?->id,
            'campus_id' => $request->route('campus')?->id ?? $request->input('campus_id') ?? $request->query('campus_id'),
            'academic_unit_id' => $request->route('academicUnit')?->id ?? $request->input('academic_unit_id') ?? $request->query('academic_unit_id'),
            'course_id' => $request->route('course')?->id ?? $request->input('course_id') ?? $request->query('course_id'),
            'class_id' => $request->input('class_id') ?? $request->query('class_id'),
        ];

        if (! $this->permissions->can($user, $permission, $scopePayload)) {
            abort(Response::HTTP_FORBIDDEN, "Missing permission {$permission} for {$scope} scope.");
        }

        return $next($request);
    }
}
