<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\ModuleSettings;

use DbCms\Core\Contracts\Services\ModuleSettingsAdministrationInterface;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class ModuleSettingsGetController extends Controller
{
    public function __construct(private readonly ModuleSettingsAdministrationInterface $service) {}

    public function __invoke(string $identifier): View
    {
        $data = $this->service->view($identifier);

        return view($data->view, $data->data);
    }
}
