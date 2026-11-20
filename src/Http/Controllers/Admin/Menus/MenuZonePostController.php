<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Menus;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Cms\MenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class MenuZonePostController extends Controller
{
    public function __invoke(Request $request, MenuService $menuService): RedirectResponse
    {
        /** @var array<string, int|string|null> $zones */
        $zones = $request->validate([
            'zones' => 'required|array',
            'zones.*' => 'nullable|string',
        ])['zones'];

        $menuService->assignZones($zones);

        return redirect()->route('admin.menus.index', ['tab' => 'zones'])
            ->with('success', 'Zonas de navegación actualizadas correctamente.');
    }
}
