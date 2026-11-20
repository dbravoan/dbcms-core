<?php

declare(strict_types=1);

namespace DbCms\Core\Maintenance\Http\Controllers;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Maintenance\Contracts\MaintenanceSettingsAdministrationInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class MaintenanceTogglePostController extends Controller
{
    public function __construct(private readonly MaintenanceSettingsAdministrationInterface $service) {}

    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $state = $this->service->toggle();

        return $request->wantsJson() || $request->ajax()
            ? response()->json(['active' => $state, 'message' => $state ? 'Modo mantenimiento activado.' : 'Modo mantenimiento desactivado.'])
            : redirect()->route('admin.settings.features.edit', ['feature' => 'maintenance'])->with('success', $state ? 'Modo Mantenimiento ACTIVADO. Los visitantes no autorizados verán la pantalla de mantenimiento (HTTP 503).' : 'Modo Mantenimiento DESACTIVADO. El portal público vuelve a estar completamente operativo.');
    }
}
