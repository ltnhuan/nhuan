<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\IntegrationApiCatalogService;
use App\Support\ApiResponse;
use Illuminate\Routing\Controller;

class IntegrationApiCatalogController extends Controller
{
    public function catalog(IntegrationApiCatalogService $catalog)
    {
        return ApiResponse::success($catalog->catalog(), 'Danh mục API tích hợp EraLMS.');
    }

    public function openApi(IntegrationApiCatalogService $catalog)
    {
        return response()->json($catalog->openApi());
    }
}
