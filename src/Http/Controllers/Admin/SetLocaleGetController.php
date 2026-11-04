<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Cms\BackofficeLocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Quick locale switch (works for guests on the login screen too).
 */
final class SetLocaleGetController extends Controller
{
    public function __construct(
        private readonly BackofficeLocaleService $locales
    ) {}

    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $this->locales->apply($request, $locale);

        return redirect()->back();
    }
}
