<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Themes;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Cms\ThemeManagerService;
use Illuminate\Contracts\View\View;

final class ThemesGetController extends Controller
{
    public function __construct(
        private readonly ThemeManagerService $themeManager
    ) {}

    public function __invoke(): View
    {
        $themes = $this->themeManager->getAvailableThemes();
        $activeTheme = $this->themeManager->getActiveTheme();

        return view('admin.themes.index', [
            'themes' => $themes,
            'activeTheme' => $activeTheme,
        ]);
    }
}
