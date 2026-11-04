<?php

declare(strict_types=1);

namespace DbCms\Core\Filters;

use DbCms\Core\Filters\Contracts\FilterableModule;
use DbCms\Core\Models\FilterPage;

/**
 * Resolves the SEO text and the index policy of a dynamic filter page.
 *
 * Texts are composed at runtime from the localized vocabulary; an admin-curated
 * override (per module + market + locale + signature) always wins. Only curated
 * pages that also pass the quality gate are indexable — every other combination
 * is `noindex, follow` to avoid duplicate/thin content.
 */
final class FilterSeoResolver
{
    /**
     * @param  array<int, FilterDimension>  $dimensions
     */
    public function resolve(
        FilterableModule $module,
        FilterSet $set,
        array $dimensions,
        int $results,
        string $marketCode,
    ): FilterSeo {
        $curated = $this->curated($module, $set, $marketCode);

        $indexable = $curated !== null
            && $curated->is_indexable
            && $results >= (int) config('front_filters.limits.min_results', 3)
            && $set->dimensionCount() <= (int) config('front_filters.limits.max_dimensions', 3);

        $composed = $this->compose($module, $set, $dimensions);

        return new FilterSeo(
            title: (string) ($curated?->title ?: $composed['title']),
            heading: (string) ($curated?->heading ?: $composed['heading']),
            description: (string) ($curated?->meta_description ?: $composed['description']),
            robots: $indexable ? 'index, follow' : 'noindex, follow',
            indexable: $indexable,
            curated: $curated !== null,
        );
    }

    /**
     * Curated row for this filter page (module + market + locale + signature),
     * or null when the combination has no editorial override.
     */
    public function curated(FilterableModule $module, FilterSet $set, string $marketCode): ?FilterPage
    {
        return FilterPage::query()
            ->where('module', $module->filterModuleKey())
            ->where('locale', $set->locale)
            ->where('signature', $set->signature())
            ->where(function ($query) use ($marketCode): void {
                $query->where('market_code', $marketCode)->orWhereNull('market_code');
            })
            ->first();
    }

    /**
     * Compose a natural, localized text from the filter vocabulary.
     *
     * @param  array<int, FilterDimension>  $dimensions
     * @return array{title: string, heading: string, description: string}
     */
    private function compose(FilterableModule $module, FilterSet $set, array $dimensions): array
    {
        $locale = $set->locale;
        $moduleLabel = $module->filterModuleLabel($locale);

        $phrases = [];

        foreach ($dimensions as $dimension) {
            if (! $set->has($dimension->key)) {
                continue;
            }

            $value = $this->phraseFor($dimension, $set->get($dimension->key), $locale, $set->market);

            if ($value !== '') {
                $phrases[] = $this->phraseLabel($dimension->key, $locale).' '.$value;
            }
        }

        $joined = implode(', ', $phrases);

        return match ($locale) {
            'en' => [
                'title' => $joined !== '' ? "{$joined} — {$moduleLabel}" : $moduleLabel,
                'heading' => $joined !== '' ? "{$moduleLabel}: {$joined}" : $moduleLabel,
                'description' => $joined !== ''
                    ? "{$moduleLabel} content filtered by {$joined}."
                    : "{$moduleLabel} content.",
            ],
            'ca' => [
                'title' => $joined !== '' ? "{$joined} — {$moduleLabel}" : $moduleLabel,
                'heading' => $joined !== '' ? "{$moduleLabel}: {$joined}" : $moduleLabel,
                'description' => $joined !== ''
                    ? "Contingut de {$moduleLabel} filtrat per {$joined}."
                    : "Contingut de {$moduleLabel}.",
            ],
            default => [
                'title' => $joined !== '' ? "{$joined} — {$moduleLabel}" : $moduleLabel,
                'heading' => $joined !== '' ? "{$moduleLabel}: {$joined}" : $moduleLabel,
                'description' => $joined !== ''
                    ? "Contenido de {$moduleLabel} filtrado por {$joined}."
                    : "Contenido de {$moduleLabel}.",
            ],
        };
    }

    private function phraseFor(FilterDimension $dimension, mixed $value, string $locale, string $marketCode): string
    {
        if ($dimension->type === 'terms') {
            $labels = $dimension->labels($locale, $marketCode);
            $values = array_map(static fn ($slug): string => $labels[(string) $slug] ?? (string) $slug, (array) $value);

            return implode(', ', array_filter($values));
        }

        if ($dimension->type === 'bool') {
            return '';
        }

        if ($dimension->type === 'range' && is_string($value)) {
            $unit = $dimension->unit($locale);
            [$min, $max] = array_pad(explode('-', $value, 2), 2, '');

            return trim(($min !== '' ? $min : '').($max !== '' ? '-'.$max : '').' '.$unit);
        }

        return (string) $value;
    }

    private function phraseLabel(string $key, string $locale): string
    {
        $labels = [
            'es' => [
                'category' => 'Categoría', 'tag' => 'Etiqueta', 'search' => 'Búsqueda',
                'sort' => 'Ordenado por', 'price' => 'Precio', 'featured' => 'Destacados',
                'date_from' => 'Desde', 'date_to' => 'Hasta',
            ],
            'en' => [
                'category' => 'Category', 'tag' => 'Tag', 'search' => 'Search',
                'sort' => 'Sorted by', 'price' => 'Price', 'featured' => 'Featured',
                'date_from' => 'From', 'date_to' => 'To',
            ],
            'ca' => [
                'category' => 'Categoria', 'tag' => 'Etiqueta', 'search' => 'Cerca',
                'sort' => 'Ordenat per', 'price' => 'Preu', 'featured' => 'Destacats',
                'date_from' => 'Des de', 'date_to' => 'Fins a',
            ],
        ];

        return $labels[$locale][$key] ?? ($labels['es'][$key] ?? ucfirst($key));
    }
}
