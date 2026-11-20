<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Install;

use DbCms\Core\Contracts\Services\InstallationDatabaseServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Http\Requests\Install\DatabaseSetupRequest;
use Illuminate\Http\JsonResponse;

final class DatabaseTestPostController extends Controller
{
    public function __construct(private readonly InstallationDatabaseServiceInterface $service) {}

    public function __invoke(DatabaseSetupRequest $request): JsonResponse
    {
        $result = $this->service->test($request->toDto());

        return response()->json($result->connectionResponse(), $result->success ? 200 : 422);
    }
}
