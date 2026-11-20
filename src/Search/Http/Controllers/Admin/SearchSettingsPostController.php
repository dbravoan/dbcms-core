<?php

declare(strict_types=1);

namespace DbCms\Core\Search\Http\Controllers\Admin;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Search\SearchIndexer;
use DbCms\Core\Services\Cms\CoreFeatureSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Activation and maintenance of the general search, from the
 * "Filtros y búsquedas" inbox.
 */
final class SearchSettingsPostController extends Controller
{
    public function __construct(
        private readonly CoreFeatureSettings $settings,
        private readonly SearchIndexer $indexer,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:save,reindex'],
            'is_indexable' => ['nullable', 'boolean'],
            'track_terms' => ['nullable', 'boolean'],
            'results_per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'min_results_to_index' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validated['action'] === 'reindex') {
            $counts = $this->indexer->indexAll(fresh: false);

            return redirect()->back()->with('success', 'Indice de busqueda actualizado: '.array_sum($counts).' documentos.');
        }

        $updates = [
            'is_indexable' => $request->boolean('is_indexable'),
            'track_terms' => $request->boolean('track_terms'),
        ];
        foreach (['results_per_page', 'min_results_to_index'] as $key) {
            if (isset($validated[$key])) {
                $updates[$key] = (int) $validated[$key];
            }
        }
        $this->settings->setMany('search', $updates);

        return redirect()->back()->with(
            'success',
            $request->boolean('is_indexable')
                ? 'Busqueda general activada para Google.'
                : 'Busqueda general en noindex (solo para visitantes).'
        );
    }
}
