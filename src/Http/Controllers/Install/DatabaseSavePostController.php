<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Install;

use DbCms\Core\Contracts\Services\InstallationDatabaseServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Http\Requests\Install\DatabaseSetupRequest;
use Illuminate\Http\RedirectResponse;

final class DatabaseSavePostController extends Controller
{
    public function __construct(private readonly InstallationDatabaseServiceInterface $service) {}

    public function __invoke(DatabaseSetupRequest $request): RedirectResponse
    {
        $this->service->save($request->toDto());

        return redirect()->route('install.step3');
    }
}
