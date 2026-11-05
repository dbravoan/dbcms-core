<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Install;

use DbCms\Core\Contracts\Services\InstallationProvisioningServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MigrationsPostController extends Controller
{
    public function __construct(private readonly InstallationProvisioningServiceInterface $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->service->provision($request->boolean('with_demo', false));

        return response()->json($result->provisioningResponse(), $result->success ? 200 : 500);
    }
}
