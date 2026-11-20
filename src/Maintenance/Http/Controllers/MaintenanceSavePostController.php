<?php

declare(strict_types=1);

namespace DbCms\Core\Maintenance\Http\Controllers;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Maintenance\Contracts\MaintenanceSettingsAdministrationInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class MaintenanceSavePostController extends Controller
{
    public function __construct(private readonly MaintenanceSettingsAdministrationInterface $service) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $this->service->update($request->except(['_token', '_method']));

        return redirect()->route('admin.settings.features.edit', ['feature' => 'maintenance'])->with('success', 'Configuración de Mantenimiento & Construcción guardada correctamente.');
    }
}
