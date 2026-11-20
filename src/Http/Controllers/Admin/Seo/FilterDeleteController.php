<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Seo;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\FilterPage;
use Illuminate\Http\RedirectResponse;

/**
 * Removes a curated landing: the filter page keeps working at runtime (composed
 * text, noindex) but stops being an indexable curated surface.
 */
final class FilterDeleteController extends Controller
{
    public function __invoke(FilterPage $filterPage): RedirectResponse
    {
        $path = (string) ($filterPage->path ?? $filterPage->signature);

        $filterPage->delete();

        return redirect()->route('admin.seo.filters.index')
            ->with('success', 'Landing de filtro eliminada: '.' '.$path);
    }
}
