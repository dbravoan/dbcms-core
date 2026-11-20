<?php

declare(strict_types=1);

namespace DbCms\Core\Maintenance\Http\Controllers;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Maintenance\Contracts\MaintenanceSettingsAdministrationInterface;
use Illuminate\Http\Response;

final class MaintenancePreviewGetController extends Controller
{
    public function __construct(private readonly MaintenanceSettingsAdministrationInterface $service) {}

    public function __invoke(): Response
    {
        return response($this->service->preview(), 200, ['Content-Type' => 'text/html; charset=UTF-8', 'X-CMS-Maintenance-Preview' => '1', 'Cache-Control' => 'no-cache, no-store, must-revalidate']);
    }
}
