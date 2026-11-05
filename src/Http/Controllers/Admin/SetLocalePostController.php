<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Cms\BackofficeLocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Persists the authenticated user's backoffice language preference.
 */
final class SetLocalePostController extends Controller
{
    public function __construct(
        private readonly BackofficeLocaleService $locales
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
        ]);

        $this->locales->apply($request, (string) $validated['locale']);

        return redirect()->back();
    }
}
