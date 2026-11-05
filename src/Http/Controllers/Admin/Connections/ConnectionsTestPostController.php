<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Connections;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Http\RedirectResponse;

final class ConnectionsTestPostController extends Controller
{
    public function __construct(
        private readonly ExternalStorageService $storage
    ) {}

    public function __invoke(): RedirectResponse
    {
        $result = $this->storage->testConnection();

        return redirect()->route('admin.connections.index')
            ->with('storage_test_result', $result)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
