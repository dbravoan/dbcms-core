<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Themes;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Cms\ThemeManagerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ThemeActivatePostController extends Controller
{
    public function __construct(
        private readonly ThemeManagerService $themeManager
    ) {}

    public function __invoke(Request $request, string $theme): RedirectResponse
    {
        $this->themeManager->setActiveTheme($theme);

        return redirect()->route('admin.themes.index')->with('success', "Tema [{$theme}] activado exitosamente.");
    }
}
