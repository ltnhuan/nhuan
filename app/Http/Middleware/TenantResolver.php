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
        $header = config('eralms.tenant_header', 'X-Tenant-Code');
        $code = $request->header($header);
        $host = $request->getHost();
        $subdomain = $this->subdomainCode($host);

        $tenant = Tenant::query()
            ->where('status', 'active')
            ->when($code, fn ($query) => $query->where('code', strtoupper($code)))
            ->when(! $code && $host, function ($query) use ($host, $subdomain) {
                $query->where('domain', $host)
                    ->orWhere('code', strtoupper((string) $subdomain));
            })
            ->first();

        if (! $tenant) {
            $tenant = Tenant::query()
                ->where('code', config('eralms.default_tenant_code', 'VABIS'))
                ->where('status', 'active')
                ->first();
        }

        $this->context->set($tenant);
        $request->attributes->set('tenant', $tenant);
        app()->instance('eralms.tenant', $tenant);

        return $next($request);
    }

    private function subdomainCode(?string $host): ?string
    {
        if (! $host || substr_count($host, '.') < 2) {
            return null;
        }

        return explode('.', $host)[0] ?: null;
    }
}
