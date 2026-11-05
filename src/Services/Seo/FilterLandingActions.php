<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Seo;

use DbCms\Core\Filters\FilterPathResolver;
use DbCms\Core\Filters\FilterSet;
use DbCms\Core\Models\FilterPage;
use Illuminate\Validation\ValidationException;

final class FilterLandingActions
{
    public function __construct(private readonly FilterPathResolver $filterPaths) {}

    /**
     * Curates (or de-indexes) a filter landing from its friendly path, exactly
     * like the "Indexar en Google" button of the front office.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function seoFilterUpsert(array $args): array
    {
        $module = (string) ($args['module'] ?? '');
        $path = (string) ($args['path'] ?? '');
        $marketCode = (string) ($args['market'] ?? '');
        $locale = (string) ($args['locale'] ?? app()->getLocale());

        $resolved = $this->filterPaths->resolve($module, $marketCode, $locale, $path);

        if ($resolved === null) {
            throw ValidationException::withMessages([
                'path' => 'The path is not a valid, unambiguous filter path within the SEO limits.',
            ]);
        }

        /** @var array{set: FilterSet, canonical: string} $resolved */
        $page = FilterPage::query()->updateOrCreate(
            [
                'module' => $module,
                'market_code' => $marketCode !== '' ? $marketCode : null,
                'locale' => $locale,
                'signature' => $resolved['set']->signature(),
            ],
            [
                'path' => $resolved['canonical'],
                'title' => $args['title'] ?? null,
                'heading' => $args['heading'] ?? null,
                'meta_description' => $args['meta_description'] ?? null,
                'is_indexable' => filter_var($args['is_indexable'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
        );

        return [
            'id' => (int) $page->getKey(),
            'module' => $module,
            'path' => (string) $page->getAttribute('path'),
            'signature' => (string) $page->getAttribute('signature'),
            'is_indexable' => (bool) $page->getAttribute('is_indexable'),
        ];
    }
}
