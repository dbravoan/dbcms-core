<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Install;

use DbCms\Core\Contracts\Services\InstallationViewServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class AdminGetController extends Controller
{
    public function __construct(private readonly InstallationViewServiceInterface $service) {}

    public function __invoke(): View
    {
        return view('install.step4_admin', $this->service->administrator());
    }
}
