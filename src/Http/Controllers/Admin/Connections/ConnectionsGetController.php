<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Connections;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Media\MediaMigrationService;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Contracts\View\View;

final class ConnectionsGetController extends Controller
{
    public function __construct(
        private readonly ExternalStorageService $storage,
        private readonly MediaMigrationService $migration
    ) {}

    public function __invoke(): View
    {
        return view('admin.connections.index', [
            'configuration' => $this->storage->configuration(),
            'providers' => $this->storage->providers(),
            'providerLabels' => $this->storage->providerLabels(),
            'isConfigured' => $this->storage->isConfigured(),
            'migrationRun' => $this->migration->latestRun()?->toProgressArray(),
            'pendingFiles' => count($this->migration->candidates()),
        ]);
    }
}
