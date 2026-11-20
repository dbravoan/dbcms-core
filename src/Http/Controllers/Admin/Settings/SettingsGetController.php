<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Settings;

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class SettingsGetController extends Controller
{
    public function __construct(
        private readonly GeneralSettingsServiceInterface $settingsService
    ) {}

    public function __invoke(): View
    {
        $all = [];
        $rawGrouped = $this->settingsService->allGrouped();
        foreach ($rawGrouped as $items) {
            foreach ($items as $k => $v) {
                $all[$k] = $v;
            }
        }

        $pages = [];
        if (class_exists(Page::class)) {
            try {
                $pages = Page::with('translations')->where('is_published', true)->get();
            } catch (\Throwable) {
                // Table might not be ready
            }
        }

        return view('admin.settings.index', [
            'settings' => $all,
            'pages' => $pages,
        ]);
    }
}
