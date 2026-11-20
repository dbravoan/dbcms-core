<?php

declare(strict_types=1);

namespace DbCms\Core\Filters;

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Filters\Contracts\FilterableModule;
use DbCms\Core\Models\Market;

/**
 * Turns a resolved filter request into crawlable, canonical facet links.
 *
 * Every link is a real `<a href>` to the canonical friendly URL of the modified
 * filter set (never a query string), so search engines discover the whole
 * filter space through internal linking while every landing keeps its own
 * canonical/robots policy.
 */
final class FilterFacets
{
    public function __construct(
        private readonly FilterUrlBuilder $builder,
    ) {}

    /**
     * Active filters as removable chips.
     *
     * @param  array<int, FilterDimension>  $dimensions
     * @return array<int, array{label: string, url: string}>
     */
    public function activeChips(
        FilterableModule $module,
        FilterSet $set,
        array $dimensions,
        Market $market,
        LocalizationServiceInterface $localization,
    ): array {
        $chips = [];

        foreach ($dimensions as $dimension) {
            if (! $set->has($dimension->key)) {
                continue;
            }

            $values = $set->get($dimension->key);

            if ($dimension->type === 'terms') {
                $labels = $dimension->labels($set->locale, (string) $market->code);

                foreach ((array) $values as $slug) {
                    $chips[] = [
                        'label' => $labels[(string) $slug] ?? (string) $slug,
                        'url' => $this->url($module, $set, $market, $localization, remove: [$dimension->key => $slug]),
                    ];
                }

                continue;
            }

            $chips[] = [
                'label' => $this->describe($dimension, $values, $set->locale),
                'url' => $this->url($module, $set, $market, $localization, forget: [$dimension->key]),
            ];
        }

        return $chips;
    }

    public function clearAllUrl(
        FilterableModule $module,
        FilterSet $set,
        Market $market,
        LocalizationServiceInterface $localization,
    ): string {
        return $this->url($module, $set, $market, $localization, reset: true);
    }

    /**
     * Available facet values per dimension (links toggling each value).
     *
     * @param  array<int, FilterDimension>  $dimensions
     * @return array<int, array{key: string, label: string, values: array<int, array{label: string, url: string, active: bool}>}>
     */
    public function groups(
        FilterableModule $module,
        FilterSet $set,
        array $dimensions,
        Market $market,
        LocalizationServiceInterface $localization,
        int $limit = 10,
    ): array {
        $groups = [];

        foreach ($dimensions as $dimension) {
            // Every filterable dimension gets a control: option groups for
            // terms/enums, and a form control for dates, ranges, flags and search.
            $values = [];

            if ($dimension->type === 'terms') {
                $active = $set->list($dimension->key);

                foreach (array_slice($dimension->labels($set->locale, (string) $market->code), 0, $limit, true) as $slug => $label) {
                    $isActive = in_array((string) $slug, $active, true);

                    $values[] = [
                        'value' => (string) $slug,
                        'label' => $label,
                        'active' => $isActive,
                        'url' => $this->url(
                            module: $module,
                            set: $set,
                            market: $market,
                            localization: $localization,
                            add: ! $isActive && $dimension->multi ? [$dimension->key => (string) $slug] : [],
                            assign: ! $isActive && ! $dimension->multi ? [$dimension->key => [(string) $slug]] : [],
                            remove: $isActive ? [$dimension->key => (string) $slug] : [],
                        ),
                    ];
                }
            } else {
                foreach ($dimension->optionLabels($set->locale) as $option => $label) {
                    $isActive = $set->string($dimension->key) === (string) $option;

                    $values[] = [
                        'value' => (string) $option,
                        'label' => $label,
                        'active' => $isActive,
                        'url' => $this->url(
                            module: $module,
                            set: $set,
                            market: $market,
                            localization: $localization,
                            assign: $isActive ? [] : [$dimension->key => (string) $option],
                            forget: $isActive ? [$dimension->key] : [],
                        ),
                    ];
                }
            }

            // Terms/enums without vocabulary have nothing to offer.
            if (in_array($dimension->type, ['terms', 'enum'], true) && $values === []) {
                continue;
            }

            $groups[] = [
                'key' => $dimension->key,
                'label' => $this->dimensionLabel($dimension->key, $set->locale),
                'control' => $dimension->control(),
                'unit' => $dimension->unitFor($set->locale),
                'range' => $this->rangeState($dimension, $set),
                'current' => $dimension->type === 'enum' ? $set->string($dimension->key) : null,
                'checked' => $dimension->type === 'bool' && $set->bool($dimension->key),
                'term' => $dimension->type === 'text' ? $set->string($dimension->key) : null,
                'date' => $dimension->type === 'date' ? $set->string($dimension->key) : null,
                'values' => $values,
            ];
        }

        return $groups;
    }

    /**
     * Current min/max of a range dimension ('' when a bound is open).
     *
     * @return array{min: string, max: string}
     */
    private function rangeState(FilterDimension $dimension, FilterSet $set): array
    {
        $value = (string) $set->string($dimension->key, '');

        if ($value === '') {
            return ['min' => '', 'max' => ''];
        }

        [$min, $max] = array_pad(explode('-', $value, 2), 2, '');

        return ['min' => trim($min), 'max' => trim($max)];
    }

    /**
     * Link-toggled dimensions (terms) exported as hidden inputs, so submitting
     * the filter form preserves the current selections.
     *
     * @return array<string, array<int, string>>
     */
    public function hiddenInputs(FilterableModule $module, FilterSet $set): array
    {
        $hidden = [];

        foreach ($module->filterDimensions() as $dimension) {
            if ($dimension->type !== 'terms' || ! $set->has($dimension->key)) {
                continue;
            }

            $hidden[$dimension->key] = $set->list($dimension->key);
        }

        return $hidden;
    }

    /**
     * Filter form action: the module index URL, whose query is canonicalized
     * with a 301 by the shared {@see FilterQueryRedirector}.
     */
    public function action(
        FilterableModule $module,
        FilterSet $set,
        Market $market,
        LocalizationServiceInterface $localization,
    ): string {
        $prefix = $localization->getModulePrefix($module->filterModuleKey(), $set->locale);

        return $localization->url("/{$prefix}", $market);
    }

    /**
     * Build the canonical URL of the current filter set with a modification.
     *
     * @param  array<string, string>  $add
     * @param  array<string, string>  $remove
     * @param  array<string, string|array<int, string>>  $assign
     * @param  array<int, string>  $forget
     */
    private function url(
        FilterableModule $module,
        FilterSet $set,
        Market $market,
        LocalizationServiceInterface $localization,
        array $add = [],
        array $remove = [],
        array $assign = [],
        array $forget = [],
        bool $reset = false,
    ): string {
        $values = $reset ? [] : $set->values;

        foreach ($add as $key => $value) {
            $current = array_values(array_map('strval', (array) ($values[$key] ?? [])));
            $current[] = (string) $value;
            $values[$key] = array_values(array_unique($current));
        }

        foreach ($remove as $key => $value) {
            $current = array_values(array_map('strval', (array) ($values[$key] ?? [])));
            $values[$key] = array_values(array_diff($current, [(string) $value]));

            if ($values[$key] === []) {
                unset($values[$key]);
            }
        }

        foreach ($assign as $key => $value) {
            $values[$key] = $value;
        }

        foreach ($forget as $key) {
            unset($values[$key]);
        }

        $modified = new FilterSet($module->filterModuleKey(), (string) $market->code, $set->locale, $values);
        $prefix = $localization->getModulePrefix($module->filterModuleKey(), $set->locale);

        if ($modified->isEmpty()) {
            return $localization->url("/{$prefix}", $market);
        }

        $path = $this->builder->canonicalPath($modified, $module->filterDimensions(), $set->locale) ?? '';

        return $path === ''
            ? $localization->url("/{$prefix}", $market)
            : $localization->url("/{$prefix}/{$path}", $market);
    }

    private function describe(FilterDimension $dimension, mixed $value, string $locale): string
    {
        if ($dimension->type === 'enum') {
            return $dimension->optionLabels($locale)[(string) $value] ?? (string) $value;
        }

        return (string) $value;
    }

    private function dimensionLabel(string $key, string $locale): string
    {
        $labels = [
            'es' => [
                'category' => 'Categoría', 'tag' => 'Etiquetas', 'sort' => 'Ordenar por',
                'price' => 'Precio', 'featured' => 'Destacados', 'search' => 'Búsqueda',
                'date_from' => 'Desde', 'date_to' => 'Hasta', 'period' => 'Periodo',
                'rooms' => 'Habitaciones', 'time' => 'Tiempo', 'ingredient' => 'Ingredientes',
                'zone' => 'Zona', 'type' => 'Tipo', 'availability' => 'Disponibilidad',
            ],
            'en' => [
                'category' => 'Category', 'tag' => 'Tags', 'sort' => 'Sort by',
                'price' => 'Price', 'featured' => 'Featured', 'search' => 'Search',
                'date_from' => 'From', 'date_to' => 'To', 'period' => 'Period',
                'rooms' => 'Rooms', 'time' => 'Time', 'ingredient' => 'Ingredients',
                'zone' => 'Area', 'type' => 'Type', 'availability' => 'Availability',
            ],
            'ca' => [
                'category' => 'Categoria', 'tag' => 'Etiquetes', 'sort' => 'Ordena per',
                'price' => 'Preu', 'featured' => 'Destacats', 'search' => 'Cerca',
                'date_from' => 'Des de', 'date_to' => 'Fins a', 'period' => 'Període',
                'rooms' => 'Habitacions', 'time' => 'Temps', 'ingredient' => 'Ingredients',
                'zone' => 'Zona', 'type' => 'Tipus', 'availability' => 'Disponibilitat',
            ],
        ];

        return $labels[$locale][$key] ?? ($labels['es'][$key] ?? ucwords(str_replace('_', ' ', $key)));
    }
}
