<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Settings;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Http\Requests\Admin\SiteSettingsUpdateRequest;
use DbCms\Core\Services\Cms\SiteSettingsService;
use Illuminate\Http\RedirectResponse;

final class SettingsPutController extends Controller
{
    public function __invoke(SiteSettingsUpdateRequest $request, SiteSettingsService $settings): RedirectResponse
    {
        $settings->update($request->settings());

        return redirect()->route('admin.settings.index')
            ->with('success', 'Ajustes del sitio y empresa actualizados correctamente.');
    }
}
