<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;

class TenantResolver
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $code = $request->header('X-Tenant-Code');
        $host = $request->getHost();

        $tenant = Tenant::query()
            ->when($code, fn ($query) => $query->where('code', $code))
            ->when(! $code && $host, fn ($query) => $query->where('domain', $host))
            ->where('status', 'active')
            ->first();

        if (! $tenant) {
            $tenant = Tenant::query()->where('code', config('eralms.default_tenant_code', 'VABIS'))->first();
        }

        $this->context->set($tenant);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
