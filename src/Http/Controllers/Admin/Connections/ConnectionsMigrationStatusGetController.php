<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Connections;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Media\MediaMigrationService;
use Illuminate\Http\JsonResponse;

final class ConnectionsMigrationStatusGetController extends Controller
{
    public function __construct(
        private readonly MediaMigrationService $service
    ) {}

    public function __invoke(): JsonResponse
    {
        $run = $this->service->latestRun();

        return response()->json([
            'run' => $run?->toProgressArray(),
        ]);
    }
}
