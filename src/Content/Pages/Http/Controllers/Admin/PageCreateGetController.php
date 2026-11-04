<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Http\Controllers\Admin;

use DbCms\Core\Content\Pages\Services\PageViewRegistry;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use Illuminate\Contracts\View\View;

final class PageCreateGetController extends Controller
{
    public function __invoke(): View
    {
        $markets = Market::with('languages')->where('is_active', true)->get();
        $languages = Language::where('is_active', true)->get();

        return view('admin.pages.create', [
            'markets' => $markets,
            'languages' => $languages,
            'pageViews' => app(PageViewRegistry::class)->all(),
        ]);
    }
}
