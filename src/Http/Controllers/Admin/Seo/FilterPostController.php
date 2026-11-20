<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Seo;

use DbCms\Core\Filters\FilterPathResolver;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\FilterPage;
use DbCms\Core\Sections\SectionSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Indexa un filtro en Google: crea (o actualiza) la página curada.
 *
 * The administrator types the address as it appears on the site; it is resolved
 * with the module's own dimensions, so the stored signature is exactly the one
 * the runtime resolver computes. The intro is composed with the CMS block
 * manager and persisted through the shared {@see SectionSyncService}.
 */
final class FilterPostController extends Controller
{
    public function __construct(
        private readonly FilterPathResolver $resolver,
        private readonly SectionSyncService $sections,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module' => 'required|string|max:50',
            'market_code' => 'nullable|string|max:20',
            'locale' => 'required|string|max:10',
            'path' => 'required|string|max:191',
            'title' => 'nullable|string|max:255',
            'heading' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_indexable' => 'nullable|boolean',
            'intro_blocks_json' => 'nullable|string',
            'sections_order_locale' => 'nullable|string|max:20',
        ]);

        $marketCode = (string) ($validated['market_code'] ?? '');
        $resolved = $this->resolver->resolve(
            (string) $validated['module'],
            $marketCode,
            (string) $validated['locale'],
            (string) $validated['path'],
        );

        if ($resolved === null) {
            throw ValidationException::withMessages([
                'path' => 'La ruta no es válida para ese módulo: revisa marcadores, valores y límites.',
            ]);
        }

        $page = FilterPage::updateOrCreate(
            [
                'module' => (string) $validated['module'],
                'market_code' => $marketCode !== '' ? $marketCode : null,
                'locale' => (string) $validated['locale'],
                'signature' => $resolved['set']->signature(),
            ],
            [
                'path' => $resolved['canonical'],
                'title' => $validated['title'] ?? null,
                'heading' => $validated['heading'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
                'is_indexable' => (bool) ($validated['is_indexable'] ?? false),
            ]
        );

        $this->sections->sync($page, (string) ($validated['intro_blocks_json'] ?? '[]'), orderLocale: $validated['sections_order_locale'] ?? null);

        return redirect()->route('admin.seo.filters.index')
            ->with('success', 'Filtro preparado para indexación: '.$resolved['canonical']);
    }
}
