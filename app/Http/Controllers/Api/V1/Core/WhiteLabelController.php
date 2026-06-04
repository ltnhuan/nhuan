<?php

namespace App\Http\Controllers\Api\V1\Core;

use App\Services\Core\WhiteLabelService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WhiteLabelController extends Controller
{
    public function update(Request $request, TenantContext $tenantContext, WhiteLabelService $whiteLabel)
    {
        $data = $request->validate([
            'logo_url' => ['nullable', 'string', 'max:500'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'locale' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:80'],
            'settings' => ['nullable', 'array'],
        ]);

        return $whiteLabel->update($tenantContext->get(), $data);
    }
}
