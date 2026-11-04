<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Api\V1;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Seo\JsonLdSchemaService;
use Illuminate\Http\JsonResponse;

final class SiteSchemaGetController extends Controller
{
    public function __invoke(JsonLdSchemaService $service): JsonResponse
    {
        return response()->json($service->generateSiteGraph());
    }
}
