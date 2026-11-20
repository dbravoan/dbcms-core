<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Seo;

use DbCms\Core\Filters\FilterPathResolver;
use DbCms\Core\Filters\FilterSeoResolver;
use DbCms\Core\Models\FilterPage;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Sections\SectionEditorService;

/**
 * Filtros y búsquedas: las páginas que genera el front al filtrar o buscar.
 *
 * By default they are `noindex`; here the editor picks which ones Google should
 * index, with their own title, description and — composed with the CMS block
 * manager — a rich intro rendered above the results.
 *
 * The form is server-rendered (no dialogs): it opens empty (`?new=1`), editing a
 * curated page (`?edit=<id>`) or pre-filled from the *Indexar en Google* button
 * on the site itself, so the editor never types an address by hand.
 */
final class FilterEditorService
{
    public function __construct(
        private readonly FilterPathResolver $resolver,
        private readonly FilterSeoResolver $seo,
        private readonly SectionEditorService $sections,
    ) {}

    /** @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function data(array $query, string $locale): array
    {
        $modules = [];

        foreach ($this->resolver->modules() as $key => $module) {
            $modules[$key] = $module->filterModuleLabel($locale);
        }

        $languages = Language::query()->where('is_active', true)->orderBy('code')->get();

        return [
            'filterPages' => FilterPage::query()->orderBy('module')->orderBy('locale')->get(),
            'filterModules' => $modules,
            'markets' => Market::query()->where('is_active', true)->orderBy('code')->get(),
            'languages' => $languages,
            'form' => $this->form($query, $locale),
        ];
    }

    /** @param array<string, mixed> $query
     * @return array<string, mixed>|null
     */
    private function form(array $query, string $locale): ?array
    {
        $editing = ($query['edit'] ?? null);

        if ($editing !== null && $editing !== '') {
            $page = FilterPage::query()->find((int) $editing);

            if ($page instanceof FilterPage) {
                return $this->editingForm($page);
            }
        }

        $path = trim((string) ($query['path'] ?? ''));
        $moduleKey = trim((string) ($query['module'] ?? ''));

        if ($path !== '' && $moduleKey !== '') {
            return $this->prefilledForm($query, $locale, $moduleKey, $path);
        }

        if (filter_var($query['new'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return [
                'mode' => 'create',
                'id' => null,
                'module' => array_key_first($this->resolver->modules()) ?? '',
                'market_code' => '',
                'locale' => $locale,
                'path' => '',
                'title' => '',
                'heading' => '',
                'meta_description' => '',
                'is_indexable' => true,
                'sections' => null,
                'warning' => false,
                'prefilled' => false,
            ];
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function editingForm(FilterPage $page): array
    {
        return [
            'mode' => 'edit',
            'id' => (int) $page->getKey(),
            'module' => (string) $page->module,
            'market_code' => (string) ($page->market_code ?? ''),
            'locale' => (string) $page->locale,
            'path' => (string) ($page->path ?? $page->signature),
            'title' => (string) ($page->title ?? ''),
            'heading' => (string) ($page->heading ?? ''),
            'meta_description' => (string) ($page->meta_description ?? ''),
            'is_indexable' => (bool) $page->is_indexable,
            'sections' => $this->sections->payload($page),
            'warning' => str_contains((string) $page->signature, 'search=') || str_contains((string) $page->signature, 'sort='),
            'prefilled' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    private function prefilledForm(array $query, string $locale, string $moduleKey, string $path): ?array
    {
        $marketCode = trim((string) ($query['market_code'] ?? ''));
        $requestLocale = trim((string) ($query['locale'] ?? '')) ?: $locale;

        $resolved = $this->resolver->resolve($moduleKey, $marketCode, $requestLocale, $path);

        if ($resolved === null) {
            return null;
        }

        $market = $marketCode !== ''
            ? Market::query()->where('code', $marketCode)->first()
            : Market::query()->where('is_default', true)->first();

        $results = $market !== null
            ? $this->resolver->resultCount($resolved, $market, $requestLocale)
            : 0;

        $composed = $this->seo->resolve(
            $resolved['module'],
            $resolved['set'],
            $resolved['module']->filterDimensions(),
            $results,
            $marketCode !== '' ? $marketCode : (string) $market?->code,
        );

        return [
            'mode' => 'create',
            'id' => null,
            'module' => $moduleKey,
            'market_code' => $marketCode,
            'locale' => $requestLocale,
            'path' => $resolved['canonical'],
            'title' => $composed->title,
            'heading' => $composed->heading,
            'meta_description' => $composed->description,
            'is_indexable' => true,
            'sections' => null,
            'warning' => $resolved['set']->has('search') || $resolved['set']->has('sort'),
            'prefilled' => true,
        ];
    }
}
