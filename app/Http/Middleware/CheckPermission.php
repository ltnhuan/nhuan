<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission, string $scope = 'tenant')
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $allowed = Cache::remember("eralms:permissions:{$tenant?->id}:{$user->id}", 300, function () use ($user, $tenant) {
            return DB::table('user_role_scope')
                ->join('role_permission', 'user_role_scope.role_id', '=', 'role_permission.role_id')
                ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
                ->where('user_role_scope.user_id', $user->id)
                ->where(function ($query) use ($tenant) {
                    $query->whereNull('user_role_scope.tenant_id')
                        ->orWhere('user_role_scope.tenant_id', $tenant?->id);
                })
                ->pluck('permissions.key')
                ->unique()
                ->values()
                ->all();
        });

        if (! in_array($permission, $allowed, true)) {
            abort(Response::HTTP_FORBIDDEN, "Missing permission {$permission} for {$scope} scope.");
        }

        return $next($request);
    }
}
